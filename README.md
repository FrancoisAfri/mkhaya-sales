# Mkhaya Hub - Enterprise ERP System

## Overview

Mkhaya Hub is a comprehensive Enterprise Resource Planning (ERP) system built with Laravel, featuring AI-powered copilot assistance, policy management, HR management, asset tracking, and leave management capabilities.

## System Requirements

- PHP >= 8.1
- Composer
- MySQL >= 5.7 / MariaDB >= 10.2
- Node.js & NPM (for frontend assets)
- Redis (recommended for queue/cache)

## Technology Stack

- **Backend Framework:** Laravel
- **Database:** MySQL/MariaDB
- **Queue Driver:** Redis/Database
- **Cache Driver:** Redis/File
- **AI Integration:** Multiple providers (OpenAI, Claude, Gemini, DeepSeek)
- **Authentication:** Laravel Sanctum, 2FA, OTP

## Project Structure

```
├── app/
│   ├── Core/                    # Core system modules
│   │   ├── AI/                  # AI Copilot & Agent system
│   │   ├── Audit/               # Audit trail & logging
│   │   ├── Auth/                # Authentication & authorization
│   │   ├── Category/            # Category management
│   │   ├── CompanyUnits/        # Company unit structure
│   │   ├── DocumentType/        # Document type management
│   │   ├── Holidays/            # Public holiday management
│   │   ├── Leave/               # Leave management
│   │   ├── ManagerUser/         # Manager user operations
│   │   ├── Module/              # Module management
│   │   ├── Permission/          # RBAC permissions
│   │   ├── Position/            # HR positions
│   │   ├── Ribbon/              # Navigation ribbon
│   │   ├── Role/                # Role management
│   │   ├── Security/            # Security & access control
│   │   ├── Setup/               # System setup
│   │   ├── User/                # User management
│   │   ├── UserAccess/          # User access control
│   │   ├── UserApproval/        # Approval workflows
│   │   └── UserManagement/      # User administration
│   ├── Http/                    # HTTP layer
│   ├── Models/                  # Eloquent models
│   ├── Modules/                 # Feature modules
│   │   ├── Assets/              # Asset management
│   │   ├── ContactPerson/       # Contact management
│   │   ├── Division/            # Division structure
│   │   ├── Finances/            # Financial management
│   │   ├── Fleet/               # Fleet management
│   │   ├── HR/                  # Human resources
│   │   └── Policies/            # Policy management
│   ├── Providers/               # Service providers
│   └── Shared/                  # Shared utilities & traits
├── bootstrap/                   # Laravel bootstrap
├── config/                      # Configuration files
├── database/                    # Migrations & seeders
├── docker-compose.yml           # Docker configuration
├── Dockerfile                   # Docker build file
└── artisan                      # Laravel CLI
```

## Core Features

### AI Copilot System
- Multi-provider AI integration (OpenAI, Claude, Gemini, DeepSeek)
- Agent registry pattern for AI service management
- ERP context builder for intelligent responses
- Audit logging for all AI interactions

### Policy Management
- Policy lifecycle management (create, update, versioning)
- Clause-based policy structure
- Rule engine with condition evaluation
- Violation tracking and enforcement
- Natural language rule parsing
- Policy exceptions management

### Human Resources
- Employee management with position tracking
- Job categories and position hierarchy
- Company units and division structure
- Employee change tracking (promotions, transfers)
- Document management system

### Leave Management
- Leave type configuration
- Leave application workflow
- Leave balance tracking
- Leave history and audit
- Custom leave policies
- Public holiday integration

### Asset Management
- Asset inventory tracking
- License management
- Asset depreciation calculation
- Asset transfer tracking
- Component tracking
- File attachments for assets
- Asset log for audit trail

### Security & Access Control
- Role-Based Access Control (RBAC)
- Module-level permissions
- Two-factor authentication
- Password history enforcement
- OTP verification
- Session management
- Activity logging

## Installation

1. **Clone the repository**
```bash
git clone <repository-url>
cd mkhaya-hub-clean
```

2. **Install dependencies**
```bash
composer install
npm install
```

3. **Environment configuration**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configure database in .env**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mkhaya_hub
DB_USERNAME=root
DB_PASSWORD=
```

5. **Run migrations & seeders**
```bash
php artisan migrate --seed
```

6. **Start the application**
```bash
php artisan serve
npm run dev
```

## Docker Deployment

```bash
docker-compose up -d
docker-compose exec app php artisan migrate --seed
```

## AI Configuration

Configure AI providers in `config/ai.php` and `.env`:

```env
# OpenAI
OPENAI_API_KEY=your-key
OPENAI_MODEL=gpt-4

# Anthropic Claude
ANTHROPIC_API_KEY=your-key
ANTHROPIC_MODEL=claude-3

# Google Gemini
GEMINI_API_KEY=your-key

# DeepSeek
DEEPSEEK_API_KEY=your-key
```

## Database Migrations

Migrations are organized by module:
- `database/migrations/core/` - Core system tables
- `database/migrations/hr/` - HR-related tables
- `database/migrations/leaves/` - Leave management
- `database/migrations/assets/` - Asset management
- `database/migrations/policies/` - Policy management
- `database/migrations/audit/` - Audit logging
- `database/migrations/security/` - Security tables

## Key Design Patterns

| Pattern | Implementation |
|---------|----------------|
| Repository Pattern | `UserRepositoryInterface`, `EloquentUserRepository` |
| Service Layer | `UserService`, `PolicyService`, `PermissionService` |
| Strategy Pattern | AI Agents (`OpenAIAgent`, `ClaudeAgent`, etc.) |
| Registry Pattern | `AgentRegistry` for AI service management |
| DTO Pattern | `AgentRequest`, `AgentResponse`, `PolicyEventDTO` |
| Action Pattern | Policy actions (`CreatePolicyAction`, `UpdatePolicyAction`) |

## Code Style & Standards

- **PHP:** PSR-12 coding standards
- **Naming Conventions:**
  - Controllers: `XxxController.php`
  - Models: `Xxx.php` (singular)
  - Migrations: `YYYY_MM_DD_hhmmss_description.php`
  - Services: `XxxService.php`
  - Repositories: `EloquentXxxRepository.php`
  - Traits: `CamelCase.php` with `Trait` suffix
  - Interfaces: `XxxInterface.php`

## Security Features

- Password complexity enforcement
- Password history (prevents reuse)
- Account lockout after failed attempts
- Session timeout configuration
- IP-based access restrictions (configurable)
- CSRF protection
- XSS prevention
- SQL injection protection (Eloquent/parameterized queries)
- Activity logging for sensitive operations

## Support

For technical support or inquiries, contact the system administrator.

---

**Version:** 1.0.0  
**Framework:** Laravel  
**License:** Proprietary
