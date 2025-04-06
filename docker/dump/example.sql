USE orm;

CREATE TABLE users
(
    id       INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    email    VARCHAR(255) NOT NULL
);

CREATE TABLE profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bio VARCHAR(255),
    birthday DATE,
    user_id INT UNIQUE,
    FOREIGN KEY (user_id) REFERENCES users(id)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

CREATE TABLE photos
(
    id      INT AUTO_INCREMENT PRIMARY KEY,
    url     VARCHAR(255) NOT NULL,
    user_id INT          NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE user_group (
    user_id INT,
    group_id INT,
    PRIMARY KEY (user_id, group_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (group_id) REFERENCES groups(id)
);
