<?php
/**
 * api/vr-showroom.php — VR Showroom API
 */
require_once __DIR__ . '/../includes/middleware.php';

$action = $_GET['action'] ?? 'products';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

switch ($action) {

    case 'products':
        $page  = max(1, (int)get('page', 1));
        $limit = min(50, max(1, (int)get('limit', 12)));
        $sql   = "SELECT p.id, p.name, p.slug, p.price, p.currency, p.thumbnail, p.short_desc,
                         COALESCE(p.has_vr, 0) has_vr, COALESCE(p.has_ar, 0) has_ar,
                         p.vr_model_url, p.ar_model_url, p.rating
                  FROM products p
                  WHERE p.status = 'active'
                  ORDER BY COALESCE(p.has_vr, 0) DESC, COALESCE(p.has_ar, 0) DESC, p.rating DESC";
        jsonResponse(paginate($db, $sql, [], $page, $limit));
        break;

    case 'product':
        $id   = (int)get('id', 0);
        $slug = get('slug', '');
        if (!$id && !$slug) jsonResponse(['error' => 'Product ID or slug required'], 400);

        $stmt = $db->prepare(
            "SELECT p.id, p.name, p.slug, p.price, p.currency, p.thumbnail, p.images,
                    p.short_desc, p.description,
                    COALESCE(p.has_vr, 0) has_vr, COALESCE(p.has_ar, 0) has_ar,
                    p.vr_model_url, p.ar_model_url, p.rating, p.review_count,
                    s.company_name supplier_name
             FROM products p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             WHERE " . ($id ? 'p.id = ?' : 'p.slug = ?') . " AND p.status = 'active'"
        );
        $stmt->execute([$id ?: $slug]);
        $product = $stmt->fetch();
        if (!$product) jsonResponse(['error' => 'Product not found'], 404);
        jsonResponse(['data' => $product]);
        break;

    case 'showrooms':
        // Return a list of virtual showrooms grouped by supplier
        $stmt = $db->prepare(
            "SELECT s.id, s.company_name, s.logo, s.description,
                    COUNT(p.id) product_count
             FROM suppliers s
             JOIN products p ON p.supplier_id = s.id AND p.status = 'active'
             WHERE s.status = 'active'
             GROUP BY s.id
             ORDER BY product_count DESC
             LIMIT 20"
        );
        $stmt->execute();
        jsonResponse(['data' => $stmt->fetchAll()]);
        break;

    case 'stats':
        $vrCount       = $db->query("SELECT COUNT(*) FROM products WHERE status='active' AND has_vr=1")->fetchColumn();
        $arCount       = $db->query("SELECT COUNT(*) FROM products WHERE status='active' AND has_ar=1")->fetchColumn();
        $allCount      = $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
        $showroomCount = $db->query("SELECT COUNT(DISTINCT supplier_id) FROM products WHERE status='active'")->fetchColumn();
        jsonResponse(['data' => [
            'vr_products'    => (int)$vrCount,
            'ar_products'    => (int)$arCount,
            'total_products' => (int)$allCount,
            'showrooms'      => (int)$showroomCount,
        ]]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
