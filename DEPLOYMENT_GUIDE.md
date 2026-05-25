# Ratepoint System - Deployment Guide

Your Laravel PHP application has been configured for cloud hosting. The error you were experiencing on Netlify was because **Netlify only hosts static sites**, but your app requires a backend server to run PHP code.

## What Was Fixed

✅ **Procfile** - Tells the host how to run your app  
✅ **runtime.txt** - Specifies PHP 8.2 version  
✅ **.htaccess** - Handles URL routing  
✅ **composer.lock** - Dependencies lock file  
✅ **.buildpacks** - Build configuration  

---

## Deployment Options (Choose One)

### **Option 1: Railway (Recommended - Easiest)**

1. Go to https://railway.app and sign up with GitHub
2. Click "New Project" → "Deploy from GitHub repo"
3. Select your Ratepoint repository
4. Railway will automatically detect the Procfile and deploy
5. Add environment variables in Railway dashboard:
   - `DB_HOST` - Your database host
   - `DB_USERNAME` - Database user
   - `DB_PASSWORD` - Database password
   - `DB_DATABASE` - Database name

**Cost:** Pay-as-you-go (~$5/month for small apps)  
**Time to deploy:** 2-3 minutes

---

### **Option 2: Heroku (Traditional)**

1. Go to https://www.heroku.com and sign up
2. Install Heroku CLI
3. Run:
```bash
heroku login
heroku create your-app-name
heroku addons:create heroku-postgresql:hobby-dev
git push heroku main
```
4. Add environment variables:
```bash
heroku config:set DB_HOST=your-db-host
heroku config:set DB_USERNAME=your-db-user
heroku config:set DB_PASSWORD=your-db-pass
heroku config:set DB_DATABASE=your-db-name
```

**Cost:** ~$7/month  
**Note:** Free tier discontinued

---

### **Option 3: Render**

1. Go to https://render.com and sign up with GitHub
2. Click "New +" → "Web Service"
3. Connect your GitHub repository
4. Select Ratepoint repo
5. Fill in:
   - **Name:** ratepoint
   - **Environment:** PHP
   - **Build Command:** `composer install`
   - **Start Command:** `heroku-php-apache2 public/`

6. Add environment variables for your database

**Cost:** $7/month  
**Time to deploy:** 5 minutes

---

## Database Configuration

Your app uses a MySQL/MariaDB database. You have options:

**Option A: Use existing database**
- Update your `.env` file with database credentials
- The `db.php` file will read these credentials

**Option B: Add a managed database**
- Railway, Heroku, and Render all offer managed databases
- Railway: Add PostgreSQL or MySQL addon
- Heroku: Add PostgreSQL addon
- Render: Add PostgreSQL connection

---

## Next Steps

1. **Test locally first:**
   ```bash
   php -S localhost:8000
   ```

2. **Choose a hosting platform** (Railway recommended)

3. **Connect your GitHub repo** to the platform

4. **Set environment variables** for database connection

5. **Deploy** - Usually automatic when you push to GitHub

---

## Troubleshooting

**Still getting 404?**
- Ensure `Procfile` exists in root directory
- Check that `public/` directory has `index.php`
- Verify `.htaccess` is properly configured

**Database connection errors?**
- Check database credentials in environment variables
- Verify database is running and accessible
- Check firewall/security group settings

**Need help?**
- Railway Support: https://railway.app/help
- Heroku Support: https://help.heroku.com/
- Render Support: https://render.com/docs

---

Generated: May 25, 2026
