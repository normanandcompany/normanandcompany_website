function loadTravelStore() {
    const container = document.getElementById('productContainer');

    fetch('/api/getProducts.php')
        .then(res => res.json())
        .then(data => {

            container.innerHTML = '';

            data.forEach(product => {

                const card = document.createElement('div');
                card.classList.add('card');

                card.innerHTML = `
                    <img src="${product.image_url}" alt="${product.product_name}">
                    <h3>${product.product_name}</h3>
                    <p>${product.product_category_id}</p>
                    <p>${product.product_description}</p>
                    <p><strong>$${parseFloat(product.price).toFixed(2)}</strong></p>
                `;

                container.appendChild(card);
            });
        })
        .catch(console.error);
}