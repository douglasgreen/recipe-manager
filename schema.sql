CREATE DATABASE RecipeManager;

USE RecipeManager;

CREATE TABLE categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    created_at  DATETIME     NOT NULL,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_categories_name UNIQUE (name)
);

CREATE TABLE recipes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    category_id  INT          NOT NULL,
    title        VARCHAR(150) NOT NULL,
    ingredients  TEXT         NOT NULL,
    instructions TEXT         NOT NULL,
    servings     INT          NOT NULL DEFAULT 1,
    created_at   DATETIME     NOT NULL,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_recipes_categories FOREIGN KEY (category_id) REFERENCES categories (id)
);

CREATE INDEX idx_recipes_category_id ON recipes (category_id);
CREATE INDEX idx_recipes_title ON recipes (title);
