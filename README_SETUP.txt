1. Edit .env
2. Create database: social_publisher
3. Import schema.sql
4. Login with:
   email: admin@example.com
   password: password
5. Setup cron:
   * * * * * /usr/bin/php /path/to/social-publisher/cron_scheduler.php >/dev/null 2>&1
   * * * * * /usr/bin/php /path/to/social-publisher/cron_publisher.php >/dev/null 2>&1
