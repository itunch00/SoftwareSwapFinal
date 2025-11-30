# SoftwareSwapFinal
### step1: Requirements: 
1. Install a full LAMP stack on openSUSE( Apache, MariaDB, PHP, PHP-extensions):
   #### Option1:
    ~> sudo zypper install apache2 apache2-mod_php mariadb mariadb-tools php8 php8-cli php8-mysql php8-mbstring php8-zip php8-xml php8-curl php8-gd composer git unzip
   #### Option2:
   ~> sudo systemctl enable apache2 mariadb
   ~> sudo systemctl start apache2 mariadb
   ~> sudo zypper install php8-fpm
   ~> sudo systemctl enable --now php-fpm
   ~> systemctl status php-fpm
   ~> sudo systemctl restart apache2
   ~> systemctl status mariadb
   ~> sudo mariadb-secure-installation
   ~> mariadb -u root -p
   ~> sudo mariadb -u root
   ~> SELECT user, host, plugin FROM mysql.user WHERE user='root';
   ~> ALTER USER 'root'@'localhost' IDENTIFIED BY '0309';
   ~> FLUSH PRIVILEGES;
#### Note:  check after install by:
    ~> php -v
   ~> composer -V
   ~> mysql --version
   ~> git --version
#### create composer.json automatically  by:
   ~> composer init --no-interaction
   ~> composer require twig/twig vlucas/phpdotenv
   
   
2. Install VS code on openSUSE: ADD extenctions(in VS code): PHP Intelephence, PHP Debug , Twig language2 and Editorconfig

#### Note 1 : Put dependencies + autoload rules in composer.json one time, " in your code you only include vendor/autoload.php once and then use the classses normally .

#### Note 2: Using (composer dump-autoload ) command if you make change on composer.json file.

3. .env file contains: configuration values like DB host, App-KEY, username and password..etc.

4. Generate App-Key by using this command: php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'m
### Step2: create DB that name as "uafs_cs_social"
### step3: Create a user which will help our code base to interact with mariaDB
CREATE USER 'uafs_app'@'localhost' IDENTIFIED BY 'insert_your_password';
GRANT ALL PRIVILEGES ON uafs_cs_social.* TO 'uafs_app'@'localhost';
FLUSH PRIVILEGES;
### Step4: Command to generate hash password
php -r 'echo password_hash("Passw0rd!", PASSWORD_DEFAULT), PHP_EOL;'

### Step5:  Commands used to run the queries for generating and seeding the data in the uafs_cs_social mariadb.

mysql -u uafs_app -p uafs_social < database/migrations/001_create_users.sql
mysql -u uafs_app -p uafs_social < database/migrations/002_create_groups.sql
mysql -u uafs_app -p uafs_social < database/migrations/003_create_channels.sql
mysql -u uafs_app -p uafs_social < database/migrations/004_create_group_memberships.sql
mysql -u uafs_app -p uafs_social < database/migrations/005_create_channel_messages.sql
mysql -u uafs_app -p uafs_social < database/migrations/006_create_user_profiles.sql
mysql -u uafs_app -p uafs_social < database/migrations/007_create_dm_conversations.sql
mysql -u uafs_app -p uafs_social < database/migrations/008_create_dm_messages.sql
mysql -u uafs_app -p uafs_social < database/migrations/009_create_moderation_actions.sql
#### New from Swap
mysql -u uafs_app -p uafs_social < database/migrations/010_create_notifications.sql
mysql -u uafs_app -p uafs_social < database/migrations/011_create_notification_assignments.sql