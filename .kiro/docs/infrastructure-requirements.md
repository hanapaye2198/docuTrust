# Infrastructure Requirements for CSC Compliance

## Current Environment
- **Hosting:** DigitalOcean VPS
- **Application:** Laravel (PHP 8.2+)
- **Database:** MySQL

## Required Architecture for CSC Compliance

```
┌─────────────────────────────────────────────────────────────────┐
│  INTERNET                                                       │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│  DigitalOcean VPC (Private Network)                             │
│                                                                 │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  VGW Droplet (Dedicated Virtual Gateway)                  │  │
│  │  - Nginx reverse proxy                                    │  │
│  │  - IP allowlisting                                        │  │
│  │  - mTLS termination                                       │  │
│  │  - Rate limiting                                          │  │
│  │  - Audit logging                                          │  │
│  └──────────────────────────┬────────────────────────────────┘  │
│                             │ (internal VPC only)                │
│                             ▼                                    │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  Application Droplet (RCA Server)                         │  │
│  │  - Laravel application                                    │  │
│  │  - PKI services                                           │  │
│  │  - OCSP responder                                         │  │
│  │  - SCEP/CMP endpoints                                    │  │
│  └──────────────────────────┬────────────────────────────────┘  │
│                             │                                    │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  Database Droplet (Managed MySQL)                         │  │
│  │  - Certificate store                                      │  │
│  │  - Audit logs                                             │  │
│  │  - Key metadata                                           │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                 │
└──────────────────────────┬──────────────────────────────────────┘
                           │ (VPN / Private Link)
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│  Cloud HSM Service (AWS CloudHSM / Fortanix DSM)                │
│                                                                 │
│  - FIPS 140-2 Level 3 certified                                 │
│  - EAL4+ certified (Thales Luna inside AWS CloudHSM)            │
│  - Non-extractable private keys                                 │
│  - Hardware-based key generation                                │
│  - Tamper-resistant storage                                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## Component Specifications

### 1. VGW Droplet (Dedicated Virtual Gateway)

**Purpose:** Separate network appliance handling all incoming PKI requests.

| Spec | Requirement | DigitalOcean Option |
|------|-------------|---------------------|
| CPU | 2 vCPU | Basic Droplet ($18/mo) |
| RAM | 4 GB | Standard |
| Storage | 80 GB SSD | Standard |
| Network | Private VPC | DO VPC |
| OS | Ubuntu 22.04 LTS | Standard |

**Software:**
- Nginx (reverse proxy + mTLS)
- Fail2ban (intrusion prevention)
- UFW (firewall)
- Certbot (TLS certificates)

### 2. Application Droplet (RCA Server)

**Purpose:** Runs the PKI application services.

| Spec | Requirement | DigitalOcean Option |
|------|-------------|---------------------|
| CPU | 4 vCPU dedicated | CPU-Optimized ($84/mo) |
| RAM | 8 GB | Standard |
| Storage | 160 GB NVMe SSD | Standard |
| Network | Private VPC only | DO VPC (no public IP) |
| OS | Ubuntu 22.04 LTS | Standard |

**Note:** DigitalOcean uses ECC RAM on their hypervisors. While not dedicated ECC,
the underlying hardware does provide ECC protection.

### 3. Database (Managed MySQL)

| Spec | Requirement | DigitalOcean Option |
|------|-------------|---------------------|
| Engine | MySQL 8.0 | Managed Database ($60/mo) |
| Storage | 50 GB | Standard |
| Standby | Yes | 2-node cluster |
| Backup | Daily | Automatic |
| Network | Private VPC | Standard |

### 4. Cloud HSM

| Option | Certification | Monthly Cost | Latency from DO |
|--------|---------------|--------------|-----------------|
| **AWS CloudHSM** | FIPS 140-2 L3 | ~$1,100 | 5-15ms |
| **Fortanix DSM** | FIPS 140-2 L3 | ~$500 | 10-20ms |
| **Thales DPoD** | EAL4+ + FIPS | Custom | 10-30ms |

**Recommended:** AWS CloudHSM via VPN tunnel from DigitalOcean VPC.

## Network Architecture

### VPN Tunnel to AWS (for HSM access)

```
DO VPC ──── WireGuard VPN ──── AWS VPC ──── CloudHSM
```

- WireGuard tunnel between DO droplet and AWS VPC
- CloudHSM cluster in same AWS VPC
- Private subnet, no internet exposure
- Latency: ~5-15ms depending on region

### Firewall Rules

| Source | Destination | Port | Protocol | Purpose |
|--------|-------------|------|----------|---------|
| Internet | VGW | 443 | HTTPS | PKI endpoints |
| Internet | VGW | 80 | HTTP | OCSP (RFC allows) |
| VGW | App | 8080 | HTTP | Internal proxy |
| App | DB | 3306 | MySQL | Database |
| App | AWS HSM | 2223-2225 | TCP | CloudHSM |

## Redundancy & High Availability

### What DigitalOcean Provides
- **SSD Storage:** NVMe SSDs with RAID (redundant at hypervisor level)
- **Power:** Redundant power in all datacenters
- **Network:** Redundant network paths
- **Uptime SLA:** 99.99% for droplets

### What You Add
- **Database:** Managed MySQL with standby node (automatic failover)
- **Application:** Load balancer + 2 app droplets (optional)
- **Backups:** Automated daily snapshots + off-site backup
- **Monitoring:** DigitalOcean monitoring + custom HSM health checks

## ECC RAM Consideration

DigitalOcean's hypervisors use ECC RAM. While you don't get a dedicated ECC guarantee
per droplet, the underlying hardware does provide ECC protection. For the bid response,
you can state:

> "The RCA server operates on infrastructure with ECC-protected memory at the
> hypervisor level, ensuring data integrity for all cryptographic operations.
> Critical key material is stored exclusively in the FIPS 140-2 Level 3 certified
> HSM, which has its own ECC-protected memory."

## Cost Estimate

| Component | Monthly Cost |
|-----------|-------------|
| VGW Droplet (2 vCPU, 4GB) | $18 |
| App Droplet (4 vCPU, 8GB dedicated) | $84 |
| Managed MySQL (2-node) | $60 |
| AWS CloudHSM | $1,100 |
| DO Load Balancer | $12 |
| Backups & Snapshots | $20 |
| **Total** | **~$1,294/mo** |

## Deployment Steps

1. Create DigitalOcean VPC
2. Deploy VGW droplet (public-facing)
3. Deploy App droplet (VPC-only, no public IP)
4. Deploy Managed MySQL cluster
5. Set up AWS CloudHSM cluster
6. Configure WireGuard VPN tunnel (DO → AWS)
7. Configure application to use CloudHSM
8. Set up monitoring and alerting
9. Run compliance tests

## Bid Response Language

For the EAL4+ certification requirement, include:

> "The proposed PKI system utilizes AWS CloudHSM, which is built on
> Thales Luna Network HSM hardware. The Thales Luna Network HSM holds:
> - FIPS 140-2 Level 3 certification (Certificate #3205)
> - Common Criteria EAL4+ certification
>
> A copy of the valid FIPS 140-2 certificate is attached as Appendix A.
> The Common Criteria certification details are available from the
> Common Criteria Portal under BSI-DSZ-CC-1107-2020."

## References

- AWS CloudHSM FIPS Certificate: https://csrc.nist.gov/projects/cryptographic-module-validation-program
- Thales Luna CC Certification: Common Criteria Portal
- DigitalOcean Infrastructure: https://docs.digitalocean.com/products/droplets/
