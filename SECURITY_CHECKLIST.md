# Bhardwaj Gurukul - Security Checklist

## Pre-Deployment Security Verification

### 1. Database Security
- [ ] Supabase project created with strong password
- [ ] Service Role Key is secured and NOT exposed in client-side code
- [ ] Row Level Security (RLS) policies enabled on all tables
- [ ] Database access restricted to specific IPs if possible
- [ ] Regular backups enabled

### 2. Configuration Security
- [ ] Update `api/config.php` with actual Supabase credentials
- [ ] Ensure `SUPABASE_SERVICE_KEY` is not exposed in public pages
- [ ] Set appropriate `MAX_FILE_SIZE` limits
- [ ] Review `ALLOWED_EXTENSIONS` list

### 3. Authentication Security
- [ ] Super admin password changed from default
- [ ] Session timeout configured appropriately (default: 24 hours)
- [ ] Login rate limiting enabled (5 attempts, 15 minute lockout)
- [ ] CSRF tokens implemented on all forms
- [ ] Session cookies configured with:
  - HttpOnly flag
  - Secure flag (if using HTTPS)
  - SameSite policy

### 4. File Upload Security
- [ ] File type validation enabled
- [ ] MIME type verification active
- [ ] File size limits enforced (50MB max)
- [ ] Upload directory has proper permissions (755)
- [ ] Files stored in Supabase Storage (not web-accessible paths)
- [ ] Virus scanning enabled (if hosting platform supports it)

### 5. Web Server Security
- [ ] HTTPS enabled with valid SSL certificate
- [ ] Security headers configured:
  - X-Frame-Options
  - X-Content-Type-Options
  - X-XSS-Protection
  - Strict-Transport-Security
- [ ] Directory browsing disabled
- [ ] Error messages don't expose sensitive information
- [ ] PHP display_errors disabled in production

### 6. Code Security
- [ ] All user input sanitized
- [ ] SQL injection prevention via prepared statements
- [ ] XSS prevention via HTML escaping
- [ ] No hardcoded credentials in code
- [ ] Secure password hashing (bcrypt, cost 12)

### 7. Access Control
- [ ] Admin dashboard protected by authentication
- [ ] API endpoints protected where needed
- [ ] Public endpoints only expose necessary data
- [ ] Rate limiting on sensitive endpoints

## Post-Deployment Monitoring

### 1. Regular Tasks
- Monitor login attempts for suspicious activity
- Review uploaded files regularly
- Check database for unusual activity
- Update dependencies when security patches available
- Review access logs for unauthorized attempts

### 2. Backup Verification
- Verify automated backups are running
- Test backup restoration procedure
- Store backups in secure, separate location

### 3. Security Audits
- Conduct quarterly security reviews
- Test login recovery procedures
- Review user access levels
- Update and rotate credentials periodically

## Emergency Response Plan

### If Security Incident Detected:

1. **Immediate Actions:**
   - Disable affected admin accounts
   - Review recent access logs
   - Check for unauthorized data access

2. **Containment:**
   - Change all admin passwords
   - Rotate API keys if compromised
   - Enable additional logging

3. **Recovery:**
   - Restore from clean backup if needed
   - Patch identified vulnerabilities
   - Verify system integrity

4. **Post-Incident:**
   - Document the incident
   - Update security procedures
   - Train staff on security awareness

## Contact Information

For security concerns:
- Email: bhardwajgurukulbgs@gmail.com
- Phone: 9934220425

## Security Best Practices

1. **Never share:**
   - Supabase Service Role Key
   - Admin passwords
   - Database credentials

2. **Always:**
   - Use strong, unique passwords
   - Keep software updated
   - Monitor for suspicious activity
   - Follow principle of least privilege

3. **Remember:**
   - Security is an ongoing process
   - Regular reviews are essential
   - Stay informed about new threats