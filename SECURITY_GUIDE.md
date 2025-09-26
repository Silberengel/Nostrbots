# Nostrbots Security Guide

This guide covers the security enhancements available for the Nostrbots production setup, including Docker secrets, privilege dropping, and comprehensive logging.

## 🔐 **Docker Secrets Implementation**

### **What are Docker Secrets?**
Docker secrets provide a secure way to store and manage sensitive data:
- **Encrypted at rest**: Stored encrypted in Docker's internal database
- **Encrypted in transit**: Securely transmitted to containers
- **Memory-only access**: Secrets only exist in memory, never on disk
- **Access control**: Only containers with secret access can read them
- **Audit trail**: Docker logs secret access

### **Setting up Docker Secrets**

#### **1. Initialize Docker Swarm**
```bash
# Docker secrets require swarm mode
docker swarm init
```

#### **2. Create Secrets from Environment Variables**
```bash
# Run the secrets setup script
sudo ./setup-docker-secrets.sh
```

This script will:
- Initialize Docker swarm mode if needed
- Create secrets from your `.env` file
- Test secret access
- Provide usage instructions

#### **3. Use Secrets in Docker Compose**
```bash
# Use the secrets-enabled compose file
docker stack deploy -c docker-compose.secrets.yml nostrbots
```

### **How Secrets Work in Containers**

#### **Secret Access in Containers**
```bash
# Secrets are mounted as files in /run/secrets/
/run/secrets/nostr_bot_key_encrypted
/run/secrets/nostr_bot_npub

# Read secrets in your application
NOSTR_BOT_KEY_ENCRYPTED=$(cat /run/secrets/nostr_bot_key_encrypted)
NOSTR_BOT_NPUB=$(cat /run/secrets/nostr_bot_npub)
```

#### **Benefits Over .env Files**
- **No file persistence**: Secrets never written to disk
- **Encrypted storage**: Stored encrypted in Docker's database
- **Access control**: Only authorized containers can access
- **Audit logging**: Docker logs all secret access

---

## 🛡️ **Security Hardening**

### **Comprehensive Security Setup**

#### **1. Run Security Hardening**
```bash
sudo ./setup-security-hardening.sh
```

This script implements:
- **Firewall configuration** (UFW)
- **Intrusion prevention** (fail2ban)
- **Comprehensive logging**
- **Automatic security updates**
- **System hardening**
- **Security monitoring**

#### **2. Firewall Configuration**
```bash
# Check firewall status
ufw status

# Allow only necessary ports
ufw allow ssh
ufw allow from 127.0.0.1 to any port 8080  # Jenkins (localhost only)
ufw allow from 127.0.0.1 to any port 3334  # Orly relay (localhost only)
```

#### **3. Intrusion Prevention**
```bash
# Check fail2ban status
fail2ban-client status

# View banned IPs
fail2ban-client status sshd
```

---

## 📊 **Enhanced Logging & Monitoring**

### **Audit Logging**
```bash
# View audit logs
tail -f /var/log/nostrbots/audit.log

# Log key access
/opt/nostrbots/scripts/audit-logger.sh key_access "nsec" "jenkins"

# Log service actions
/opt/nostrbots/scripts/audit-logger.sh service_action "start" "nostrbots"
```

### **Security Monitoring**
```bash
# View security monitor logs
tail -f /var/log/nostrbots/security-monitor.log

# Manual security check
/opt/nostrbots/scripts/security-monitor.sh
```

### **Log Rotation**
- **Daily rotation**: Logs rotated daily
- **30-day retention**: Keep 30 days of logs
- **Compression**: Old logs compressed to save space
- **Automatic cleanup**: Old logs automatically removed

---

## 🔒 **Container Security**

### **Privilege Dropping**
```yaml
# Security constraints in docker-compose.secrets.yml
security_opt:
  - no-new-privileges:true
cap_drop:
  - ALL
cap_add:
  - CHOWN
  - SETGID
  - SETUID
read_only: true
tmpfs:
  - /tmp
  - /var/run
```

### **Resource Limits**
```yaml
# Prevent resource exhaustion
deploy:
  resources:
    limits:
      cpus: '2.0'
      memory: 4G
    reservations:
      cpus: '1.0'
      memory: 2G
```

### **Network Isolation**
```yaml
# Internal network with no external access
networks:
  nostrbots-network:
    driver: bridge
    internal: true
```

---

## 🚨 **Security Monitoring & Alerts**

### **Automated Monitoring**
The security monitor checks every 5 minutes for:
- **Failed login attempts**: Alerts if >10 failed attempts per day
- **Container status**: Alerts if unusual number of containers running
- **Disk space**: Alerts if >90% disk usage
- **Memory usage**: Alerts if >90% memory usage

### **Manual Security Checks**
```bash
# Check system security
nostrbots monitor

# Check firewall status
ufw status verbose

# Check fail2ban status
fail2ban-client status

# Check audit logs
tail -f /var/log/nostrbots/audit.log

# Check security monitor logs
tail -f /var/log/nostrbots/security-monitor.log
```

---

## 🔧 **Security Management Commands**

### **Docker Secrets Management**
```bash
# List secrets
docker secret ls

# Inspect a secret
docker secret inspect nostr_bot_key_encrypted

# Remove secrets
docker secret rm nostr_bot_key_encrypted nostr_bot_npub

# Update secrets (create new, remove old)
echo "new_key" | docker secret create nostr_bot_key_encrypted_v2 -
docker service update --secret-rm nostr_bot_key_encrypted --secret-add nostr_bot_key_encrypted_v2 nostrbots_jenkins
```

### **Firewall Management**
```bash
# Check status
ufw status

# Enable/disable
ufw enable
ufw disable

# Add/remove rules
ufw allow 8080
ufw delete allow 8080
```

### **Fail2ban Management**
```bash
# Check status
fail2ban-client status

# Unban an IP
fail2ban-client set sshd unbanip 192.168.1.100

# Ban an IP manually
fail2ban-client set sshd banip 192.168.1.100
```

---

## 📋 **Security Checklist**

### **Initial Setup**
- [ ] Run `sudo ./setup-docker-secrets.sh`
- [ ] Run `sudo ./setup-security-hardening.sh`
- [ ] Verify firewall is active: `ufw status`
- [ ] Verify fail2ban is running: `fail2ban-client status`
- [ ] Test secret access: `docker secret ls`

### **Regular Maintenance**
- [ ] Check audit logs weekly: `tail -f /var/log/nostrbots/audit.log`
- [ ] Review security monitor logs: `tail -f /var/log/nostrbots/security-monitor.log`
- [ ] Verify automatic updates: `grep unattended-upgrades /var/log/syslog`
- [ ] Check disk space: `df -h /opt/nostrbots`
- [ ] Review failed login attempts: `grep "Failed password" /var/log/auth.log`

### **Incident Response**
- [ ] Check audit logs for suspicious activity
- [ ] Review fail2ban banned IPs
- [ ] Verify container integrity
- [ ] Check for unauthorized access
- [ ] Review backup integrity

---

## 🚨 **Security Incident Response**

### **If You Suspect a Breach**
1. **Immediate Response**
   ```bash
   # Stop all services
   nostrbots stop
   
   # Check audit logs
   tail -f /var/log/nostrbots/audit.log
   
   # Check for suspicious activity
   grep "SECURITY" /var/log/nostrbots/audit.log
   ```

2. **Investigation**
   ```bash
   # Check failed login attempts
   grep "Failed password" /var/log/auth.log
   
   # Check Docker logs
   docker logs nostrbots-jenkins
   docker logs nostrbots-orly-relay
   
   # Check system integrity
   nostrbots monitor
   ```

3. **Recovery**
   ```bash
   # Restore from backup if needed
   nostrbots restore /opt/nostrbots/backups/latest-backup.json.gz
   
   # Restart services
   nostrbots start
   
   # Verify security
   nostrbots monitor
   ```

---

## 📞 **Security Support**

### **Getting Help**
1. **Check logs first**: Most issues are logged
2. **Run diagnostics**: Use `nostrbots monitor`
3. **Review this guide**: Common issues covered
4. **Check system status**: Verify all services running

### **Emergency Contacts**
- **System logs**: `/var/log/nostrbots/`
- **Audit logs**: `/var/log/nostrbots/audit.log`
- **Security monitor**: `/var/log/nostrbots/security-monitor.log`
- **System logs**: `/var/log/syslog`

---

**Remember**: Security is an ongoing process. Regular monitoring, updates, and maintenance are essential for maintaining a secure production environment.
