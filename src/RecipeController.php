<?php

declare(strict_types=1);

namespace DouglasGreen\RecipeManager;

use PDO;
use PDOException;

class RecipeController
{
    private PDO $pdo;

    public function __construct()
    {
        $ini = parse_ini_file(__DIR__ . '/../config.ini', true, INI_SCANNER_TYPED);
        if ($ini === false || !isset($ini['db'])) {
            $db = ['host' => 'localhost', 'port' => '3306', 'dbname' => 'recipes', 'username' => 'root', 'password' => ''];
        } else {
            $db = $ini['db'];
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['dbname']);

        try {
            $this->pdo = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            die("DB Connection failed. Check config.ini");
        }
    }

    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            try {
                switch ($action) {
                    case 'add_category':
                        $name = trim($_POST['category_name'] ?? '');
                        if ($name === '') {
                            $this->flash('danger', 'Category name is required.');
                        } else {
                            $stmt = $this->pdo->prepare('INSERT INTO categories (name, created_at) VALUES (:name, NOW())');
                            $stmt->execute([':name' => $name]);
                            $this->flash('success', 'Category created.');
                        }
                        $this->redirectWith();
                        break;

                    case 'update_category':
                        $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
                        $name       = trim($_POST['category_name'] ?? '');
                        if (!$categoryId || $name === '') {
                            $this->flash('danger', 'Valid category and name are required.');
                        } else {
                            $stmt = $this->pdo->prepare('UPDATE categories SET name = :name WHERE id = :id');
                            $stmt->execute([':name' => $name, ':id' => $categoryId]);
                            $this->flash('success', 'Category renamed.');
                        }
                        $this->redirectWith(['category_id' => $categoryId]);
                        break;

                    case 'delete_category':
                        $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
                        if (!$categoryId) {
                            $this->flash('danger', 'Invalid category.');
                            $this->redirectWith();
                        }
                        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM recipes WHERE category_id = :id');
                        $countStmt->execute([':id' => $categoryId]);
                        if ((int)$countStmt->fetchColumn() > 0) {
                            $this->flash('danger', 'Cannot delete: This category has recipes.');
                        } else {
                            $stmt = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
                            $stmt->execute([':id' => $categoryId]);
                            $this->flash('success', 'Category deleted.');
                            $this->redirectWith(['category_id' => null]);
                            return;
                        }
                        $this->redirectWith();
                        break;

                    case 'add_recipe':
                        $categoryId   = filter_input(INPUT_POST, 'recipe_category', FILTER_VALIDATE_INT);
                        $title        = trim($_POST['title'] ?? '');
                        $ingredients  = trim($_POST['ingredients'] ?? '');
                        $instructions = trim($_POST['instructions'] ?? '');
                        $servings     = max(1, (int)($_POST['servings'] ?? 1));

                        if (!$categoryId || $title === '') {
                            $this->flash('danger', 'Title and Category are required.');
                        } else {
                            $stmt = $this->pdo->prepare(
                                'INSERT INTO recipes (category_id, title, ingredients, instructions, servings, created_at)
                                 VALUES (:category_id, :title, :ingredients, :instructions, :servings, NOW())'
                            );
                            $stmt->execute([
                                ':category_id' => $categoryId,
                                ':title'       => $title,
                                ':ingredients' => $ingredients,
                                ':instructions'=> $instructions,
                                ':servings'    => $servings,
                            ]);
                            $this->flash('success', 'Recipe added.');
                        }
                        $this->redirectWith(['category_id' => $categoryId]);
                        break;

                    case 'update_recipe':
                        $recipeId     = filter_input(INPUT_POST, 'recipe_id', FILTER_VALIDATE_INT);
                        $categoryId   = filter_input(INPUT_POST, 'recipe_category', FILTER_VALIDATE_INT);
                        $title        = trim($_POST['title'] ?? '');
                        $ingredients  = trim($_POST['ingredients'] ?? '');
                        $instructions = trim($_POST['instructions'] ?? '');
                        $servings     = max(1, (int)($_POST['servings'] ?? 1));

                        if (!$recipeId || !$categoryId || $title === '') {
                            $this->flash('danger', 'Missing required fields.');
                        } else {
                            $stmt = $this->pdo->prepare(
                                'UPDATE recipes SET category_id = :cid, title = :t, ingredients = :ing, instructions = :ins, servings = :s WHERE id = :id'
                            );
                            $stmt->execute([
                                ':cid' => $categoryId, ':t' => $title, ':ing' => $ingredients, ':ins' => $instructions, ':s' => $servings, ':id' => $recipeId,
                            ]);
                            $this->flash('success', 'Recipe updated.');
                        }
                        $this->redirectWith(['category_id' => $categoryId]);
                        break;

                    case 'delete_recipe':
                        $recipeId = filter_input(INPUT_POST, 'recipe_id', FILTER_VALIDATE_INT);
                        if ($recipeId) {
                            $stmt = $this->pdo->prepare('DELETE FROM recipes WHERE id = :id');
                            $stmt->execute([':id' => $recipeId]);
                            $this->flash('success', 'Recipe deleted.');
                        }
                        $this->redirectWith();
                        break;
                }
            } catch (PDOException $e) {
                $this->flash('danger', 'Database error: ' . $e->getMessage());
                $this->redirectWith();
            }
        }
    }

    public function getIndexData(): array
    {
        $selectedCategoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
        $searchQuery        = trim((string)($_GET['q'] ?? ''));

        $flashMessages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        $categoriesStmt = $this->pdo->query(
            'SELECT c.id, c.name, COUNT(r.id) AS recipe_count
             FROM categories AS c
             LEFT JOIN recipes AS r ON r.category_id = c.id
             GROUP BY c.id ORDER BY c.name'
        );
        $categories = $categoriesStmt->fetchAll();

        if (!$selectedCategoryId && !empty($categories) && $searchQuery === '') {
            $selectedCategoryId = (int)$categories[0]['id'];
        }

        $recipeSql = [
            'SELECT r.id, r.title, r.ingredients, r.instructions, r.servings, r.category_id, c.name AS category_name, r.updated_at',
            'FROM recipes AS r',
            'JOIN categories AS c ON c.id = r.category_id',
            'WHERE 1=1'
        ];
        $params = [];

        if ($selectedCategoryId) {
            $recipeSql[] = 'AND r.category_id = :category_id';
            $params[':category_id'] = $selectedCategoryId;
        }

        if ($searchQuery !== '') {
            $recipeSql[] = 'AND (r.title LIKE :search OR r.ingredients LIKE :search OR r.instructions LIKE :search)';
            $params[':search'] = '%' . $searchQuery . '%';
        }

        $recipeSql[] = 'ORDER BY r.title ASC';
        $recipeStmt = $this->pdo->prepare(implode(' ', $recipeSql));
        $recipeStmt->execute($params);
        $recipes = $recipeStmt->fetchAll();

        $currentCategoryName = 'All Recipes';
        foreach($categories as $c) {
            if ((int)$c['id'] === $selectedCategoryId) {
                $currentCategoryName = $c['name'];
                break;
            }
        }
        if ($searchQuery !== '') $currentCategoryName = 'Search Results';

        return compact('categories', 'recipes', 'selectedCategoryId', 'searchQuery', 'flashMessages', 'currentCategoryName');
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['flash'][$type][] = $message;
    }

    private function redirectWith(array $overrides = []): void
    {
        $base   = strtok($_SERVER['REQUEST_URI'], '?') ?: '';
        $query  = array_merge($_GET, $overrides);
        $query  = array_filter($query, static fn($value) => $value !== null && $value !== '');
        $target = $base . ($query ? '?' . http_build_query($query) : '');
        header('Location: ' . $target);
        exit;
    }
}
