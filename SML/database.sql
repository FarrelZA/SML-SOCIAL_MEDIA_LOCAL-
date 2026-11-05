-- Membuat database baru bernama sml (utf8mb4 agar mendukung huruf/simbol apa pun)
CREATE DATABASE IF NOT EXISTS sml CHARACTER SET utf8mb4;
USE sml; -- memilih database tersebut

-- Tabel pengguna: menyimpan akun, role (dev/user), profil, foto profil & banner
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,                 -- ID unik otomatis
  username VARCHAR(30) UNIQUE NOT NULL,              -- username tidak boleh kembar
  display_name VARCHAR(50) NOT NULL,                 -- nama tampilan
  password VARCHAR(255) NOT NULL,                    -- hash password (bukan teks asli)
  role ENUM('admin','dev','user') NOT NULL DEFAULT 'user', -- 3 jenis role
  bio VARCHAR(160) DEFAULT '',                       -- deskripsi singkat profil
  avatar VARCHAR(255) DEFAULT NULL,                  -- path foto profil (foto/gif)
  banner VARCHAR(255) DEFAULT NULL,                  -- path banner (foto/gif)
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel postingan: teks (boleh berisi link) + 1 media opsional (foto/gif/video)
CREATE TABLE posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  body TEXT,
  media VARCHAR(255) DEFAULT NULL,
  media_type ENUM('image','gif','video') DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE -- akun dihapus => post ikut terhapus
);

-- Tabel reaksi: type 1 = like, -1 = dislike. Satu user hanya satu reaksi per post
CREATE TABLE reactions (
  user_id INT NOT NULL,
  post_id INT NOT NULL,
  type TINYINT NOT NULL,
  PRIMARY KEY (user_id, post_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);

-- Tabel komentar: teks + media opsional (foto/gif/video)
CREATE TABLE comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  body TEXT,
  media VARCHAR(255) DEFAULT NULL,
  media_type ENUM('image','gif','video') DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
