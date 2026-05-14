# CSC Compliance Deployment Guide

## Overview

This guide provides step-by-step instructions for deploying the CSC-compliant PKI infrastructure for docuTrust.

## Prerequisites

### Hardware Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| HSM | Thales Luna / AWS CloudHSM / Utimaco | Redundant HSMs |
| Server | 4 CPU, 8GB RAM | 8 CPU, 16GB RAM |
| Storage | 100GB SSD | 500GB SSD (redundant) |
| Network | 1Gbps | 10Gbps (redundant) |

### Software Requirements

| Component | Version | Notes |
|-----------|---------|-------|
| PHP | 8.2+ | With OpenSSL extension |
| Laravel | 10.x+ | |
| MySQL | 8.0+ | Or PostgreSQL 14+ |
| OpenSSL | 3.0+ | FIPS-enabled |
| HSM SDK | Latest | Vendor-specific |

## Deployment Steps

### Step 1: Deploy HSM

#### Option A: Thales Luna Network HSM

1. Install Thales Luna Client
2. Configure partition
3. Set up network connectivity
4. Test HSM connectivity

```bash
# Test HSM connection
/opt/luna/bin/LunaSA_JNI_Test

# Verify partition
/opt/luna/bin/LunaSA_Partition_List
```

#### Option B: AWS CloudHSM

1. Create CloudHSM cluster
2. Initialize cluster
3. Create partition
4. Configure VPC endpoints

```bash
# Initialize cluster
aws cloudhsmv2 initialize-cluster \
    --cluster-id <cluster-id> \
    --initial-user-password <password> \
    --initial-user-username <username>
```

#### Option C: Utimaco CS:CryptoServer

1. Install Utimaco software
2. Configure server
3. Set up partitions
4. Test connectivity

```bash
# Test connection
csadmin -c <config> -p <partition> -u <user> -w <password> status
```

### Step 2: Configure HSM

Update `config/hsm.php`:

```php
'backend' => env('HSM_BACKEND', 'thales'),

'thales' => [
    'partition_label' => env('THALES_PARTITION_LABEL', 'default'),
    'partition_password' => env('THALES_PARTITION_PASSWORD', ''),
    'library_path' => env('THALES_LIBRARY_PATH', '/opt/luna/lib/libCryptoki2_64.so'),
],

'aws' => [
    'cluster_id' => env('AWS_CLOUDHSM_CLUSTER_ID', ''),
    'region' => env('AWS_CLOUDHSM_REGION', 'us-east-1'),
    'access_key_id' => env('AWS_ACCESS_KEY_ID'),
    'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
],

'utimaco' => [
    'slot_id' => env('UTIMACO_SLOT_ID', 0),
    'user_pin' => env('UTIMACO_USER_PIN', ''),
    'library_path' => env('UTIMACO_LIBRARY_PATH', '/usr/lib/libcsulutimaco.so'),
],
```

### Step 3: Run Migrations

```bash
# Run database migrations
php artisan migrate

# Verify migrations
php artisan migrate:status
```

### Step 4: Configure PKI

Update `config/docutrust.php`:

```php
'pki' => [
    'openssl_config_path' => env('DOCUTRUST_OPENSSL_CONFIG', '/etc/ssl/openssl.cnf'),
    'root_ca_private_key_path' => env('DOCUTRUST_ROOT_CA_PRIVATE_KEY_PATH', ''),
    'signing_backend' => env('DOCUTRUST_SIGNING_BACKEND', 'app_managed'),
    'root_ca_name' => env('DOCUTRUST_ROOT_CA_NAME', 'DocuTrust Root CA'),
    'root_ca_country' => env('DOCUTRUST_ROOT_CA_COUNTRY', 'PH'),
    'root_ca_valid_days' => (int) env('DOCUTRUST_ROOT_CA_VALID_DAYS', 3650),
    'signer_valid_days' => (int) env('DOCUTRUST_SIGNER_CERT_VALID_DAYS', 825),
    'key_size' => 2048, // Minimum 2048 bits for CSC
],
```

### Step 5: Configure Virtual Gateway

Update `app/Http/Middleware/VirtualGateway.php`:

```php
// Configure API key
'HSM_API_KEY' => env('HSM_API_KEY', 'your-secure-api-key'),
```

### Step 6: Test HSM Integration

```bash
# Test HSM connectivity
php artisan tinker

>>> $hsm = app(\App\Services\HsmService::class);
>>> $hsm->getStatus();
>>> $hsm->getSlotInfo();

# Test key generation
>>> $keyPair = $hsm->generateRsaKeyPair(2048);
>>> print_r($keyPair);

# Test signing
>>> $hash = str_repeat('a', 64);
>>> $signature = $hsm->sign($hash, $keyPair['privateKeyId']);
>>> echo $signature;
```

### Step 7: Run Compliance Tests

```bash
# Run HSM tests
php artisan test --filter=Hsm

# Run CSC compliance tests
php artisan test --filter=Csc

# Run all tests
php artisan test
```

### Step 8: Deploy Application

```bash
# Build application
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Deploy
php artisan deploy
```

## Configuration Reference

### Environment Variables

```bash
# HSM Configuration
HSM_BACKEND=thales
THALES_PARTITION_LABEL=default
THALES_PARTITION_PASSWORD=your-password
THALES_LIBRARY_PATH=/opt/luna/lib/libCryptoki2_64.so

# AWS CloudHSM
AWS_CLOUDHSM_CLUSTER_ID=cluster-id
AWS_CLOUDHSM_REGION=us-east-1
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret

# Utimaco
UTIMACO_SLOT_ID=0
UTIMACO_USER_PIN=your-pin
UTIMACO_LIBRARY_PATH=/usr/lib/libcsulutimaco.so

# API Configuration
HSM_API_KEY=your-secure-api-key

# PKI Configuration
DOCUTRUST_OPENSSL_CONFIG=/etc/ssl/openssl.cnf
DOCUTRUST_ROOT_CA_NAME=DocuTrust Root CA
DOCUTRUST_ROOT_CA_COUNTRY=PH
DOCUTRUST_ROOT_CA_VALID_DAYS=3650
DOCUTRUST_SIGNER_CERT_VALID_DAYS=825
DOCUTRUST_SIGNING_BACKEND=app_managed
```

## Monitoring

### HSM Health Monitoring

```bash
# Check HSM status
php artisan hsm:status

# View audit logs
php artisan hsm:audit-log

# Generate audit report
php artisan hsm:audit-report --start=2026-01-01 --end=2026-12-31
```

### System Monitoring

- **HSM Health:** Monitor HSM status and errors
- **Key Usage:** Track key generation and usage
- **Audit Logs:** Review audit logs regularly
- **Certificate Expiry:** Monitor certificate expiration

## Troubleshooting

### HSM Connection Issues

```bash
# Check HSM service
systemctl status hsm-service

# Check network connectivity
ping <hsm-ip>
telnet <hsm-ip> <port>

# Check HSM logs
tail -f /var/log/hsm/hsm.log
```

### Key Generation Failures

```bash
# Check HSM status
php artisan tinker
>>> $hsm = app(\App\Services\HsmService::class);
>>> $hsm->getStatus();

# Check HSM logs for errors
```

### Signature Verification Failures

```bash
# Verify key exists
php artisan tinker
>>> $hsm = app(\App\Services\HsmService::class);
>>> $hsm->getPublicKey('key-id');

# Check key usage in audit log
php artisan hsm:audit-log --key-id=key-id
```

## Maintenance

### Key Rotation

```bash
# Rotate signer keys
php artisan pki:rotate-keys

# Rotate CA keys (rare, requires reissuance)
php artisan pki:rotate-ca-keys
```

### Certificate Renewal

```bash
# Renew signer certificates
php artisan pki:renew-certificates

# Generate new CRL
php artisan pki:generate-crl
```

### Audit Log Rotation

```bash
# Rotate audit logs
php artisan hsm:rotate-audit-logs

# Export audit logs
php artisan hsm:export-audit-logs --format=csv
```

## Security Best Practices

1. **HSM Security**
   - Keep HSM in secure facility
   - Use strong partition passwords
   - Enable tamper detection
   - Regular security audits

2. **Key Management**
   - Never export private keys from HSM
   - Rotate keys annually
   - Securely destroy old keys
   - Monitor key usage

3. **Access Control**
   - Use role-based access control
   - Limit HSM access to authorized personnel
   - Enable audit logging
   - Review access logs regularly

4. **Network Security**
   - Use VPN for remote access
   - Implement network segmentation
   - Enable TLS for all communications
   - Regular security scans

## Support

For issues or questions:
- Security Team: security@docutrust.com
- Operations Team: operations@docutrust.com
- HSM Vendor Support: Contact HSM vendor