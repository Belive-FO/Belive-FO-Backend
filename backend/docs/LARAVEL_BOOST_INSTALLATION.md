# Laravel Boost Installation Guide

This guide covers the installation and configuration of Laravel Boost for AI-assisted development with Cursor, Claude Code, and other AI agents.

## Prerequisites

### PHP Requirements

- **PHP Version**: 8.2 or higher (8.3.6 recommended)
- **Required PHP Extensions**:
  - `mbstring` - Required for string manipulation
  - `pdo` - Database abstraction layer
  - `pdo_pgsql` - PostgreSQL driver (if using PostgreSQL/Supabase)
  - `pdo_sqlite` - SQLite driver (optional, for local development)

### Verify PHP Extensions

Check if required extensions are installed:

```bash
php -m | grep mbstring
php -m | grep pdo
php -m | grep pdo_pgsql
```

If any extensions are missing, install them:

**Ubuntu/Debian (WSL):**
```bash
sudo apt-get update
sudo apt-get install -y php8.3-mbstring php8.3-pgsql php8.3-sqlite3
```

**macOS (Homebrew):**
```bash
brew install php@8.3
pecl install pdo_pgsql
```

**Windows:**
Edit `php.ini` and uncomment:
```ini
extension=mbstring
extension=pdo_pgsql
extension=pdo_sqlite
```

## Installation Steps

### 1. Install Laravel Boost

Laravel Boost is installed as a development dependency:

```bash
cd backend
composer require laravel/boost --dev
```

### 2. Run Boost Installation

This command generates agent guidelines and MCP server configuration:

```bash
php artisan boost:install
```

During installation, you'll be prompted to:
- Select your AI agents (Cursor, Claude Code, etc.)
- Choose whether to enable guidelines
- Configure MCP server settings

### 3. Verify Installation

Check that the following files were created:

- `boost.json` - Boost configuration
- `.cursor/mcp.json` - MCP server configuration for Cursor
- `AGENTS.md` - AI agent guidelines
- `.ai/` directory - Agent skills and guidelines

## Configuration

### Cache Driver Configuration

Laravel Boost requires a working cache driver. For development, we recommend using the `file` driver to avoid database dependencies:

**In `.env`:**
```env
CACHE_STORE=file
```

**Why `file` instead of `database`?**
- No database connection required
- No PDO driver dependencies
- Faster setup for development
- MCP server starts without database errors

If you prefer `database` cache, ensure:
1. Database connection is configured
2. PDO drivers are installed (`pdo_pgsql` or `pdo_sqlite`)
3. Cache table exists: `php artisan cache:table && php artisan migrate`

### MCP Server Configuration

The MCP server configuration is automatically generated in `.cursor/mcp.json`:

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "php",
            "args": ["artisan", "boost:mcp"]
        }
    }
}
```

**For WSL/Windows Development:**

If you're running Cursor from Windows and your Laravel app is in WSL, you may need to adjust the configuration:

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "wsl",
            "args": [
                "bash",
                "-c",
                "cd /home/vince/projects/Belive-FO/backend && php artisan boost:mcp"
            ]
        }
    }
}
```

### Cursor Setup

1. Open Cursor
2. Open Command Palette (`Cmd+Shift+P` or `Ctrl+Shift+P`)
3. Type `/open MCP Settings`
4. Enable the `laravel-boost` toggle

The MCP server should now be connected and available.

## Available Boost Tools

Once configured, Laravel Boost provides powerful MCP tools:

- **`application-info`** - Get comprehensive application information
- **`database-schema`** - Read database schema
- **`database-query`** - Execute read-only SQL queries
- **`list-routes`** - List all application routes
- **`list-artisan-commands`** - List available Artisan commands
- **`search-docs`** - Search Laravel documentation
- **`tinker`** - Execute PHP code in Laravel context
- **`get-absolute-url`** - Generate absolute URLs
- **`read-log-entries`** - Read application logs
- **`browser-logs`** - Read browser console logs
- **`get-config`** - Get config values
- **`last-error`** - Get last application error

## Troubleshooting

### Issue: "Call to undefined function mb_split()"

**Solution:** Install the `mbstring` PHP extension:

```bash
sudo apt-get install php8.3-mbstring
```

### Issue: "could not find driver" (PDO errors)

**Solution:** Install PDO drivers or switch to file cache:

```bash
# Option 1: Install drivers
sudo apt-get install php8.3-pgsql php8.3-sqlite3

# Option 2: Use file cache (recommended for development)
# In .env: CACHE_STORE=file
```

### Issue: MCP Server JSON Parsing Errors

**Symptoms:**
- "Unexpected token" errors in MCP logs
- "Unexpected end of JSON input" errors

**Causes:**
- Laravel errors being output to stdout
- Missing PHP extensions
- Database connection issues

**Solution:**
1. Ensure all required PHP extensions are installed
2. Set `CACHE_STORE=file` in `.env`
3. Clear Laravel cache: `php artisan config:clear`
4. Restart Cursor and reconnect MCP server

### Issue: MCP Server Not Starting

**Check:**
1. PHP is in PATH: `which php`
2. Artisan command works: `php artisan boost:mcp --help`
3. Laravel application boots: `php artisan about`
4. Check logs: `tail -f storage/logs/laravel.log`

### Issue: "No server info found" in Cursor

**Solution:**
1. Verify `.cursor/mcp.json` exists and is valid JSON
2. Check MCP server command path is correct
3. Ensure working directory is set correctly
4. Restart Cursor completely

## Updating Boost

Keep Boost resources updated:

```bash
php artisan boost:update
```

This updates:
- AI guidelines
- Agent skills
- MCP server configuration
- Documentation references

## Environment Variables

Laravel Boost doesn't require specific environment variables, but ensure your `.env` has:

```env
# Cache configuration (recommended for Boost)
CACHE_STORE=file

# Database (if using database cache)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Supabase (for your application)
SUPABASE_URL=https://xxxxx.supabase.co
SUPABASE_KEY=your-anon-key
SUPABASE_SECRET=your-service-role-key
SUPABASE_JWT_SECRET=your-jwt-secret
```

## Best Practices

1. **Use File Cache for Development**: Avoids database setup complexity
2. **Keep Boost Updated**: Run `php artisan boost:update` regularly
3. **Check PHP Extensions**: Verify all required extensions before installation
4. **Test MCP Connection**: Verify tools are available after setup
5. **Monitor Logs**: Check `storage/logs/laravel.log` for errors

## Additional Resources

- [Laravel Boost Documentation](https://laravel.com/docs/12.x/boost)
- [MCP Protocol Documentation](https://modelcontextprotocol.io)
- [Cursor MCP Setup](https://cursor.sh/docs/mcp)

## Verification Checklist

After installation, verify:

- [ ] `composer show laravel/boost` shows the package
- [ ] `php artisan boost:mcp --help` works
- [ ] `.cursor/mcp.json` exists and is valid
- [ ] `boost.json` exists
- [ ] `AGENTS.md` exists
- [ ] PHP extensions are installed (`php -m`)
- [ ] Cache driver is configured (`CACHE_STORE=file`)
- [ ] MCP server connects in Cursor (check MCP settings)
- [ ] Boost tools are available in Cursor

## Support

If you encounter issues:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify PHP extensions: `php -m`
3. Test MCP server manually: `php artisan boost:mcp`
4. Review Cursor MCP logs in the output panel
5. Consult [Laravel Boost Documentation](https://laravel.com/docs/12.x/boost)

