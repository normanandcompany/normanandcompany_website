<?php
declare(strict_types=1);

final class NewsImageService
{
    private const MIME_EXTENSIONS=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/avif'=>'avif'];
    public function __construct(private PDO $pdo, private string $uploadDirectory, private string $publicPrefix='/images/news/') {}
    public function store(array $file,int $userId,?int $articleId,array $metadata=[]):array
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Select a valid image upload.');
        if((int)($file['size']??0)>8*1024*1024)throw new RuntimeException('News images must be 8 MB or smaller.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);if(!isset(self::MIME_EXTENSIONS[$mime]))throw new RuntimeException('Only JPEG, PNG, WebP, and AVIF images are supported.');
        $size=@getimagesize($file['tmp_name']);if(!$size||$size[0]>8000||$size[1]>8000)throw new RuntimeException('The image dimensions are invalid or exceed 8000 pixels.');
        if(!is_dir($this->uploadDirectory)&&!mkdir($this->uploadDirectory,0755,true)&&!is_dir($this->uploadDirectory))throw new RuntimeException('The news image directory is unavailable.');
        $filename=bin2hex(random_bytes(16)).'.'.self::MIME_EXTENSIONS[$mime];$destination=$this->uploadDirectory.'/'.$filename;
        if(!move_uploaded_file($file['tmp_name'],$destination))throw new RuntimeException('The image could not be stored.');chmod($destination,0644);
        $path=$this->publicPrefix.$filename;
        $stmt=$this->pdo->prepare('INSERT INTO news_media(article_id,source_name,license_notes,attribution,alt_text,local_path,mime_type,width,height,file_size,approval_status,uploaded_by_user_id) VALUES(:article,:source,:license,:attribution,:alt,:path,:mime,:width,:height,:bytes,"pending",:user)');
        $stmt->execute([':article'=>$articleId,':source'=>NewsSupport::cleanText($metadata['source_name']??'',180),':license'=>NewsSupport::cleanText($metadata['license_notes']??'',2000),':attribution'=>NewsSupport::cleanText($metadata['attribution']??'',500),':alt'=>NewsSupport::cleanText($metadata['alt_text']??'',500),':path'=>$path,':mime'=>$mime,':width'=>$size[0],':height'=>$size[1],':bytes'=>$file['size'],':user'=>$userId]);
        return ['id'=>(int)$this->pdo->lastInsertId(),'path'=>$path,'approval_status'=>'pending'];
    }
}
