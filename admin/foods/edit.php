<?php
declare(strict_types=1);
define('TB_ADMIN_ENTRY', true);
require dirname(__DIR__) . '/includes/bootstrap.php';
admin_method(['GET', 'POST']);
admin_require();
require dirname(__DIR__) . '/includes/foods.php';
$id = admin_id($_GET['id'] ?? null);
$food = admin_get_food($id);
$editing = true;
$values = $food;
$values['price'] = preg_replace('/\.00\z/', '', (string)$food['price']); // show "129", not "129.00"
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_check_csrf();
    [$values, $errors] = admin_food_input($_POST);
    if (!$errors) {
        try {
            $pdo = tidebites_pdo();
            $newImage = admin_upload(isset($_FILES['image']) && is_array($_FILES['image']) ? $_FILES['image'] : null, false);
            $clean = admin_save_food($pdo, $id, $values, $newImage);
            admin_flash($clean ? 'Food item updated. Your changes have been saved.' : 'Food item updated. An unused image could not be removed; ask your local maintainer to check storage.');
            admin_redirect('foods/index.php');
        } catch (OutOfBoundsException) { admin_fail(404, 'This food item has been deleted. Your changes were not saved.'); }
        catch (InvalidArgumentException $error) { $errors['image'] = $error->getMessage(); }
        catch (Throwable) { $errors['form'] = 'Unable to save this item. Your original data and image are unchanged. Please try again.'; }
    }
    http_response_code(isset($errors['form']) ? 503 : 422);
}
$pageTitle = 'Edit Food Item';
require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/food-form.php';
require dirname(__DIR__) . '/includes/footer.php';
