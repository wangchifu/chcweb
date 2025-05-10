## 彰化縣網站系統
### 安裝
新增資料庫名為 chcweb 編碼 utf8mb4_vietnamese_ci<br>
找到 /database/chcweb.sql ，將它 dump 進去 chcweb 資料庫<br>
<br>
git clone https://git.com/wangchifu/chcsweb.git<br>
進入目錄<br>
composer install<br>
cp .env.example .env<br>
.env 中 DB_DATABASE=chcweb<br>
.env 中 DB_USERNAME 及 DB_PASSWORD 填上正確資料<br>
php artisan key:generate<br>
php artisan storage:link<br>
sudo chmod 777 -R storage bootstrap/cache<br>
### 帳密
帳號 admin 密碼 demo1234