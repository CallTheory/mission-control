#!/bin/bash
email_content=$(cat -)
php artisan email-relay:process "$email_content"
exit 0
