# 📦 S3 Testing ( Backup to S3 ) - WordPress Plugin

## ✅ Overview

The **Backup to S3** plugin is a custom-developed WordPress plugin to **backup website data (files & databases)** to **S3 Services**. The plugin supports manual or scheduled backups, secure storage on S3, and serves the need for periodic backups for WordPress sites.

## ⚙️ Features

- Backup the entire `Wordpress install folder` folder or select
- Backup database (.sql)
- Upload directly to S3 Services
- Support scheduled backup (WordPress cron job)
- Download backup file or delete after upload
- S3 connection key encryption

## 📂 Plugin Structure

```
│
├───assets
│   ├───css
│   ├───fonts
│   ├───images
│   └───js
│
├───inc
│   ├───class-admin.php
│   └───...
│
├───composer.json
├───README.md
│
├───s3_testing.php (main file)
├───uninstall.php

```
