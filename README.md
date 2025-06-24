# ltutor
使用CodeIgniter4框架
- https://github.com/CodeIgniter4/framework/releases/tag/v4.6.1

```bash
# 安裝COMPOSER
# 安裝JWT
composer require firebase/php-jwt
# 安裝REDIS
composer require predis/predis
```

# 排程..每天週三中午計算
1. /task/getBonusBySchoolLastWeek 
2. /task/getBonusBySchoolNow 
3. /task/getExtraBonusByUser  
4. /task/getExtraBonusBySchool

# 環境參數
- \app\Config\Database.php(調整DB)
- env(調整REDIS)
