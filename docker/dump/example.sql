USE orm;

CREATE TABLE profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bio VARCHAR(255),
    birthday DATE
);

CREATE TABLE users
(
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    profile_id INT UNIQUE,
    FOREIGN KEY (profile_id) REFERENCES profiles(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
       ON DELETE CASCADE
       ON UPDATE CASCADE
);

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    PRIMARY KEY (user_id, role_id)
);