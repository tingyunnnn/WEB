<?php
declare(strict_types=1);
define('TB_ADMIN_ENTRY', true);
require dirname(__DIR__) . '/includes/bootstrap.php';
admin_method(['GET', 'POST']);
admin_require();
require dirname(__DIR__) . '/includes/foods.php';
$editing = false;
$values = ['name' => '', 'description' => '', 'price' => '', 'category' => 'meals', 'image_alt' => '', 'sort_order' => '0', 'is_active' => '1'];
$errors = [];
$formToken = $_SERVER['REQUEST_METHOD'] === 'POST' ? admin_post('form_token') : admin_form_token();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_check_csrf();
    admin_check_create_token();
    [$values, $errors] = admin_food_input($_POST);
    if (!$errors) {
        $newImage = null;
        try {
            $pdo = tidebites_pdo();
            $newImage = admin_upload(isset($_FILES['image']) && is_array($_FILES['image']) ? $_FILES['image'] : null, true);
            admin_save_food($pdo, null, $values, $newImage);
            unset($_SESSION['create_tokens'][$formToken]);
            admin_flash('Food item added. Your menu is up to date.');
            admin_redirect('foods/index.php');
        } catch (InvalidArgumentException $error) { $errors['image'] = $error->getMessage(); }
        catch (Throwable) { $errors['form'] = 'Unable to save this item. Your changes were not saved. Please try again.'; }
    }
    http_response_code(isset($errors['form']) ? 503 : 422);
}
$pageTitle = 'Add Food Item';
require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/food-form.php';
require dirname(__DIR__) . '/includes/footer.php';
