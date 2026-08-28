<?php
// Hostinger / any PHP host: copy to config.php and fill in. config.php is git-ignored.
return [
  // MySQL connection for the lead form. Required.
  'db_host'             => 'localhost',
  'db_port'             => '3306',
  'db_name'             => 'your_database_name',
  'db_user'             => 'your_mysql_username',
  'db_password'         => 'your_mysql_password',
  // Where submissions are forwarded as JSON (CRM, Zapier/Make, LeadConduit, etc.). Leave '' to skip.
  'lead_webhook_url'    => '',
  'privacy_webhook_url' => '',
  // Optional email copy of every submission. Leave '' to skip.
  'notify_email'        => '',
  'from_email'          => 'no-reply@optimalhealthchoices.com',
];
