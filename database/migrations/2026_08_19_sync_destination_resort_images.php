<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';

$pdo = normanCreateDatabaseConnection('admin');

$destinationImages = [
    1 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Amber_Cove,_Dominican_Republic.jpg',
    2 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Ambergris_Caye_from_space.jpg',
    3 => 'https://commons.wikimedia.org/wiki/Special:FilePath/AntiguaBeach.jpg',
    4 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Palm_Beach_Aruba.jpg',
    5 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Barbados_(6732472211).jpg',
    6 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Barbuda_in_2025-A_04.jpg',
    7 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Belize_City.jpg',
    8 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Bimini_island.jpg',
    9 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Bonaire.jpg',
    10 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Mexico_cancun.jpg',
    11 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Cartagena_Colombia.jpg',
    12 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Castaway_Cay_beach.jpg',
    13 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Carnival_Conquest_(ship,_2002)_docked_in_Celebration_Key_(February_2026).jpg',
    14 => 'https://commons.wikimedia.org/wiki/Special:FilePath/ISS028-E-21408_-_View_of_the_Cayman_Islands.jpg',
    15 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Cozumel_~_Mexico.jpg',
    16 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Curacao_island_(34750563702).jpg',
    17 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Dominica.jpg',
    18 => 'https://commons.wikimedia.org/wiki/Special:FilePath/TainoBeachFreeportBahamas.JPG',
    19 => 'https://commons.wikimedia.org/wiki/Special:FilePath/George_Town_Cayman_Islands.jpg',
    20 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Grand_Bahama_Island.JPG',
    21 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Turks_and_Caicos_Islands_-_Grand_Turk_-_Beach_(14788700443).jpg',
    22 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Great_Stirrup_Cay_2024.jpg',
    23 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Grenada2010.jpg',
    24 => 'https://commons.wikimedia.org/wiki/Special:FilePath/La_Guadeloupe,_c%C3%B4te_Ouest_de_Basse_Terre.jpg',
    25 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Half_Moon_Cay_Beach,_Bahamas.jpg',
    26 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Havana,_Cuba.jpg',
    27 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Isla_Mujeres,_Mexico_(Unsplash).jpg',
    28 => 'https://commons.wikimedia.org/wiki/Special:FilePath/View_of_Kingston.jpg',
    29 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Labadee,_Haiti.jpg',
    30 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Disney_Dream_at_Lookout_Cay.jpg',
    31 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Mahogany_Bay,_Roatan,_Honduras_-_panoramio.jpg',
    32 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Martinique_island_from_Martinique_-_Saint_Lucia_Channel_-_panoramio.jpg',
    33 => 'https://commons.wikimedia.org/wiki/Special:FilePath/MontegoBay_Jamaica.jpg',
    34 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Nassau,_Bahamas_aerial_view.jpg',
    35 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Jamaica_-_Negril_-_058.jpg',
    36 => 'https://commons.wikimedia.org/wiki/Special:FilePath/St._Kitts_and_Nevis_(31139412183).jpg',
    37 => 'https://commons.wikimedia.org/wiki/Special:FilePath/High-view_Ocho_Rios_Jamaica.jpg',
    38 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Ocean_Cay_overview_from_the_south_direction_(March_12,_2024).jpg',
    39 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Panama_City_skyline.jpg',
    40 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Atlantis_Paradise_Island_Bahamas_2024.jpg',
    41 => 'https://commons.wikimedia.org/wiki/Special:FilePath/CocoCay_Bahamas_2024.jpg',
    42 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Turks_and_Caicos_(43864066082).jpg',
    43 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Punta_Cana,_Dominican_Republic.jpg',
    44 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Puerto_Plata_1.jpg',
    45 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Roatan_Honduras.jpg',
    46 => 'https://commons.wikimedia.org/wiki/Special:FilePath/San_Andr%C3%A9s_Island,_Colombia.jpg',
    47 => 'https://commons.wikimedia.org/wiki/Special:FilePath/San_Juan,_Puerto_Rico.jpg',
    48 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Santo_Domingo,_Dominican_Republic.jpg',
    49 => 'https://commons.wikimedia.org/wiki/Special:FilePath/USVI-StCroix-Steeple-Panorama.jpeg',
    50 => 'https://commons.wikimedia.org/wiki/Special:FilePath/St._John_US_Virgin_Islands.JPG',
    51 => 'https://commons.wikimedia.org/wiki/Special:FilePath/St_Kitts,_St_Kitts_and_Nevis.jpg',
    52 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Saint_Lucia.jpg',
    53 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Saint_Maarten.jpg',
    54 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Grande_Case,_SXM_island_in_the_Caribbean.JPG',
    55 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Pigeon_Point_beach.jpg',
    56 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Tortola.jpg',
    57 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Vessigny_Beach_-_Southwest_coast_Trinidad.jpg',
    58 => 'https://commons.wikimedia.org/wiki/Special:FilePath/Varadero_beach,_Cuba,_2025.jpeg',
    59 => 'https://commons.wikimedia.org/wiki/Special:FilePath/The_Baths_%E2%80%94_Virgin_Gorda,_British_Virgin_Islands_%E2%80%94_beach.JPG',
];

$resortImages = [
    1 => 'https://cf-images.us-east-1.prod.boltdns.net/v1/static/6051814380001/7114465b-d265-40ff-9033-78b8b119bc31/5ca57362-fb87-4bf9-a557-4a0622758cb9/1280x720/match/image.jpg',
    2 => 'https://media.cntraveler.com/photos/63ecf3b63eca5676cc7af64a/master/pass/DJI_0311-Edit-3_RT.jpg',
    3 => 'https://baoase.com/wp-content/uploads/2023/01/Tropical-Pool-Villa-5.jpg',
    4 => 'https://www.myluxurytravel.fr/wp-content/uploads/2017/07/st-martin-belmond-la-samanna.jpg',
    5 => 'https://bolongobay.com/wp-content/uploads/2020/07/779845A620C1EEAE7F69CE654362750F-scaled.jpg',
    6 => 'https://q-xx.bstatic.com/xdata/images/hotel/max1280/294430104.jpg?k=1236731aa86abe28f09e262c6383e69d8f1ff2082d5ae583f72c93b7dbdab42c&o=',
    7 => 'https://assets.hiltonstatic.com/hilton-asset-cache/image/upload/c_fill%2Cw_4517%2Ch_3000%2Cq_90%2Cf_auto/c_fill%2Cw_1920%2Ch_1275%2Cq_90%2Cf_auto/Imagery/Property%20Photography/Hilton%20Full%20Service/S/SJNHIHH/HILT_Caribe_Exterior_HR.jpg',
    8 => 'https://a.otcdn.com/imglib/hotelphotos/5/8/030/casa-de-campo-resort-and-villa-la-romana-20240324190426752200.webp',
    9 => 'https://d25wybtmjgh8lz.cloudfront.net/sites/default/files/styles/medium_gallery_800x600_/public/prop/aerial_from%20beach.jpg',
    10 => 'https://cdn.audleytravel.com/1050/748/79/16000670-beach-front-curtain-bluff.jpg',
    11 => 'https://www.travoh.com/wp-content/uploads/2022/01/053-The-Ritz-Carlton-Dorado-Beach-Reserve-Resort-Puerto-Rico-Encanto-Pool-Complex-and-Beach-Aerial-View.jpg',
    12 => 'https://images.takeshape.io/decc50a7-e43f-4c99-b5e9-cc327d2e1b93/dev/4d670bfa-3818-4e27-b1c3-68f110bb396f/Away-Lands-Excellence-Resorts-Oyster-Bay-Jamaica-Bue-Heave-032.jpg',
    13 => 'https://2cw.co.uk/do001/images/1%2Bd444b4-excellence-punta-cana.jpg',
    14 => 'https://cdn.hotelplanner.com/Common/Images/Hotels/1283169_4.jpg',
    15 => 'https://d25wybtmjgh8lz.cloudfront.net/sites/default/files/styles/iprefer/public/property/img-mastheads/23_073_Website%20Rotation_May_Half-Moon.jpg',
    16 => 'https://assets.luxtripper.co.uk/media/05ca9998-be7d-445e-98aa-1187cd101551/en/hard_rock_hotel_%26_casino_punta_cana_1920x1080.jpg',
    17 => 'https://www.hermitagebay.com/media/2t2pnjjy/dji_0745.jpg?height=525&quality=70&rxy=0.40912413899489597%2C0.6915904194853614&v=1dc70ef41529ca0&width=420',
    18 => 'https://afar.brightspotcdn.com/dims4/default/9c9648d/2147483647/strip/true/crop/5464x3640%2B0%2B0/resize/1440x959%21/quality/90/?url=https%3A%2F%2Fk3-prod-afar-media.s3.us-west-2.amazonaws.com%2Fbrightspot%2F25%2Fba%2F17fecba5440c9584afea272224d3%2Fhyatt-zilara-cap-cana-aerial-resort-10.jpg',
    19 => 'https://hyatt-zilara-rose-hall.comcaribbean.com/data/Pics/OriginalPhoto/9999/999935/999935046/hyatt-zilara-rose-hall-hotel-montego-bay-pic-100.JPEG',
    20 => 'https://blog.allinclusiveoutlet.com/wp-content/uploads/Iberostar-Grand-Hotel-Bavaro-4.jpg',
    21 => 'https://columbiametro.s3.amazonaws.com/wp-content/uploads/2025/09/18110658/jade-aerials-24-2-scaled.jpg',
    22 => 'https://static.prod.r53.tablethotels.com/media/hotels/slideshow_images_staged/large/1463079.jpg',
    23 => 'https://www.luxurylink.com/images/sho_549b7035/7412_32-2048/image-7412_32.jpg',
    24 => 'https://bynder.onthebeach.co.uk/cdn-cgi/image/width%3D1400%2Cquality%3D80%2Cfit%3Dcover%2Cformat%3Dauto/m/672dcba0f7c10aaf/original/Moon-Palace-Jamaica-Grande.jpg',
    25 => 'https://d3hk78fplavsbl.cloudfront.net/assets/common-prod/hotel/205/h3027/h3027-1-hotel_carousel_large.jpg?version=10',
    26 => 'https://images.takeshape.io/decc50a7-e43f-4c99-b5e9-cc327d2e1b93/dev/6da234d5-f46a-4d65-bccf-2b7964a8a5d4/Park-Hyatt-St-Kitts-Resort-Hotel-Review-Away-Lands-061.jpg',
    27 => 'https://symphony.cdn.tambourine.com/round-hill-hotel-and-villas/media/roundhillhotelandvillas-10-hotel-03-roundhillhistory-03-today-63f52bc1dcf2a.jpg',
    28 => 'https://assets.talentronic.com/photos/employers/262187/471615_l.jpg',
    29 => 'https://cdn.sandals.com/sandals/v13/images/EN/uploads/SBD_3ad82f401e.jpg',
    30 => 'https://www.vincentvacations.com/sandals/images/Sandals-Emerald-Bay-The-Bahamas-Aerial-Main-Pool.jpg',
    31 => 'https://www.resortsdaily.com/images/Sandals-Grande-St-Lucia-Rodney-Bay.jpg',
    32 => 'https://www.gettingstamped.com/wp-content/uploads/2022/04/Sandals-Montego-Bay-Resort-Drone-Photo-of-Overall-Property-800x450.jpg',
    33 => 'https://sandals-beach-resort-and-spa.hotels-in-jamaica.com/data/Photos/OriginalPhoto/12777/1277704/1277704630.JPEG',
    34 => 'https://mma.prnewswire.com/media/1735430/Sandals_Royal_Bahamian.jpg?p=twitter',
    35 => 'https://travelwith2ofus.com/hotels/images/sandals-royal-caribbean.jpg',
    36 => 'https://www.rli.uk.com/wp-content/uploads/2022/05/sanctuary-cap-cana-castle-aerial-view-2-scaled.jpg',
    37 => 'https://symphony.cdn.tambourine.com/sandy-lane/media/sandylane-homepagegallery-02-64ecf8d7d16c2.webp',
    38 => 'https://hotels.staticroot.com/image/upload/v1642609955/SECCC-EXT-Aerial1-6B-CB_dxruko.png',
    39 => 'https://www.visittci.com/thing/seven-stars/aerial_2048x1365.jpg',
    40 => 'https://cdn.audleytravel.com/1050/748/79/15995540-aerial-view-over-the-resort.jpg',
    41 => 'https://cdn.prestburytravel.co.uk/gallery/sugar-bay-main-image_b9d7b1ad7203610.jpg',
    42 => 'https://media.tatler.com/photos/63a2f9ff8220ce59f4462d6f/1%3A1/w_1280%2Ch_1280%2Cc_limit/SugarBeach_211222_VSB_Main%20Resort_1.jpg',
    43 => 'https://www.fourseasons.com/alt/img-opt/~75.701.867%2C7500-0%2C0000-1264%2C5000-1686%2C0000/publish/content/dam/fourseasons/images/web/BOC/BOC_1478_original.jpg',
    44 => 'https://cache.marriott.com/content/dam/marriott-digital/rz/cala/hws/a/auart/en_us/photo/unlimited/assets/rz-auart-pano-view-hero-img34129-54640.jpg',
    45 => 'https://www.myboutiquehotel.com/photos/119646/the-ritz-carlton-st-thomas-vi-016-21192-2220x1400.jpg',
    46 => 'https://photos.travelmyth.com/hotels/480/37/m1-3796847.jpg',
    47 => 'https://media.cntraveler.com/photos/5f7fe0ad5f9755e5951db382/master/pass/Tortuga%20Bay%20Puntacana%20Resort%20%26%20Club%20-%20Ae%CC%81rea.jpg',
    48 => 'https://cdn1.matadornetwork.com/blogs/1/2024/02/DJI_0009.jpg',
];

$updateDestination = $pdo->prepare("UPDATE destinations
    SET image_url = :image_url
    WHERE id = :id
      AND (image_url IS NULL OR image_url = '')");
$updateResort = $pdo->prepare("UPDATE resorts
    SET image_url = :image_url
    WHERE id = :id
      AND (image_url IS NULL OR image_url = '')");

$destinationUpdates = 0;
$resortUpdates = 0;

$pdo->beginTransaction();

try {
    foreach ($destinationImages as $id => $imageUrl) {
        $updateDestination->execute([':id' => $id, ':image_url' => $imageUrl]);
        $destinationUpdates += $updateDestination->rowCount();
    }

    foreach ($resortImages as $id => $imageUrl) {
        $updateResort->execute([':id' => $id, ':image_url' => $imageUrl]);
        $resortUpdates += $updateResort->rowCount();
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}

echo "Destination images updated: {$destinationUpdates}; resort images updated: {$resortUpdates}.\n";
