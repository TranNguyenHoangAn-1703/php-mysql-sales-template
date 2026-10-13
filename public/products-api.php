<?php

require_once '/var/www/src/config/session.php';

$pageTitle = 'Sản phẩm (API)';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="container py-4">

    <h1 class="mb-4">Sản phẩm (tải bằng API)</h1>

    <form id="filterForm" class="row g-2 mb-4">
        <div class="col-md-5">
            <input type="text" id="keyword" class="form-control" placeholder="Tìm theo tên sản phẩm">
        </div>
        <div class="col-md-4">
            <select id="category" class="form-select">
                <option value="0">Tất cả danh mục</option>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary">Lọc</button>
        </div>
    </form>

    <div id="message" class="alert alert-info d-none"></div>
    <div id="productList" class="row g-4"></div>

</main>

<script>
const categorySelect = document.getElementById('category');
const keywordInput = document.getElementById('keyword');
const productList = document.getElementById('productList');
const message = document.getElementById('message');

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function showMessage(text) {
    message.textContent = text;
    message.classList.toggle('d-none', text === '');
}

function formatPrice(value) {
    return Number(value).toLocaleString('vi-VN') + ' đ';
}

async function loadCategories() {
    const response = await fetch('/api/categories.php');
    const result = await response.json();

    if (!result.success) {
        return;
    }

    result.data.forEach(function (category) {
        const option = document.createElement('option');
        option.value = category.CategoryID;
        option.textContent = category.CategoryName;
        categorySelect.appendChild(option);
    });
}

async function loadProducts() {
    const params = new URLSearchParams();

    if (keywordInput.value.trim() !== '') {
        params.set('keyword', keywordInput.value.trim());
    }
    if (categorySelect.value !== '0') {
        params.set('category_id', categorySelect.value);
    }

    showMessage('Đang tải...');
    productList.innerHTML = '';

    try {
        const response = await fetch('/api/products.php?' + params.toString());
        const result = await response.json();

        if (!result.success || result.data.length === 0) {
            showMessage('Không tìm thấy sản phẩm phù hợp.');
            return;
        }

        showMessage('');

        productList.innerHTML = result.data.map(function (p) {
            const image = p.ImageUrl
                ? '<img src="' + escapeHtml(p.ImageUrl) + '" class="card-img-top p-3" ' +
                  'alt="' + escapeHtml(p.ProductName) + '" style="height: 220px; object-fit: contain;">'
                : '';

            return (
                '<div class="col-sm-6 col-lg-4">' +
                  '<div class="card h-100">' + image +
                    '<div class="card-body d-flex flex-column">' +
                      '<span class="badge bg-secondary align-self-start mb-2">' + escapeHtml(p.CategoryName) + '</span>' +
                      '<h5 class="card-title">' + escapeHtml(p.ProductName) + '</h5>' +
                      '<p class="card-text fw-bold text-danger">' + formatPrice(p.Price) + '</p>' +
                      '<a href="/product-detail.php?id=' + encodeURIComponent(p.ProductID) + '" ' +
                         'class="btn btn-outline-primary mt-auto">Xem chi tiết</a>' +
                    '</div>' +
                  '</div>' +
                '</div>'
            );
        }).join('');

    } catch (error) {
        showMessage('Không thể tải dữ liệu sản phẩm.');
    }
}

document.getElementById('filterForm').addEventListener('submit', function (event) {
    event.preventDefault();
    loadProducts();
});

loadCategories().then(loadProducts);
</script>

<?php

require_once '/var/www/src/includes/frontend/footer.php';
