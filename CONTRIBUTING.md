# Contributing to Free Gift Coupons Bulk Coupon Generator

Thank you for considering a contribution to Free Gift Coupons Bulk Coupon Generator. This guide keeps development, code quality, and review expectations aligned with this plugin.

## Code of Conduct

This project follows the [WordPress Community Code of Conduct](https://make.wordpress.org/handbook/community-code-of-conduct/). By participating, you are expected to uphold this code.

## Development Environment

### Requirements

- **PHP**: 8.2 or higher
- **WordPress**: 7.0 or higher
- **WooCommerce**: Required for plugin functionality
- **Free Gift Coupons for WooCommerce**: Required for end-to-end free gift coupon behavior
- **Composer**: For dependency management and quality tools
- **Git**: For version control

### Setup

1. Fork the repository on GitHub.
2. Clone your fork locally:

   ```bash
   git clone https://github.com/YOUR-USERNAME/free-gift-coupons-bulk-coupons-generator.git
   cd free-gift-coupons-bulk-coupons-generator
   ```

3. Install dependencies:

   ```bash
   composer install
   ```

4. Create a feature branch:

   ```bash
   git checkout -b feature/your-feature-name
   ```

## Coding Standards

This project follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/) for PHP, JavaScript, CSS, HTML, and accessibility.

Project-owned JavaScript should use modern ES2025+ syntax for the WordPress 7.0+ browser support baseline. Prefer native DOM APIs, `const`/`let`, arrow functions, optional chaining, nullish coalescing, template literals, and modules where appropriate. Use legacy JavaScript patterns only when a WordPress or WooCommerce integration requires them.

### Key Principles

1. **Security First**: Validate and sanitize input, escape output, protect forms with nonces, and check capabilities before privileged actions.
2. **Performance**: Use WordPress caching mechanisms, avoid unnecessary database work, and enqueue assets only where needed.
3. **Internationalization**: Mark user-facing strings with WordPress i18n functions and use the `free-gift-bulk-coupon-generator` text domain.
4. **Accessibility**: Use semantic markup and preserve keyboard and screen reader behavior in admin UI changes.

## Code Quality Tools

Run the main checks before submitting a pull request:

```bash
composer run phpcs
composer run phpstan
composer run phpmd
composer run psalm
composer test
```

## File Structure

```text
free-gift-bulk-coupon-generator/
|-- free-gift-bulk-coupon-generator.php # Main plugin file
|-- includes/                           # Plugin PHP classes
|   |-- class-fgcbg-plugin.php
|   |-- class-fgcbg-dependencies.php
|   |-- class-fgcbg-coupon-generator.php
|   |-- class-fgcbg-ajax-handler.php
|   |-- class-fgcbg-admin-assets.php
|   `-- class-fgcbg-admin-page.php
|-- assets/                             # Admin CSS and JavaScript
|-- languages/                          # Translation template
|   `-- free-gift-bulk-coupon-generator.pot
|-- tests/                              # PHPUnit tests
|-- stubs/                              # Static-analysis stubs
|-- README.md                           # Project documentation
|-- readme.txt                          # WordPress.org readme
|-- CHANGELOG.md                        # Version history
|-- CONTRIBUTING.md                     # This file
|-- LICENSE                             # GPL license
|-- composer.json                       # PHP dependencies
|-- phpcs.xml                           # PHPCS configuration
|-- phpstan.neon                        # PHPStan configuration
|-- phpmd.xml                           # PHPMD configuration
`-- .github/                            # GitHub workflows and templates
```

## Making Changes

### Before You Start

1. Check existing [issues](https://github.com/EngineScript/free-gift-coupons-bulk-coupons-generator/issues) and [pull requests](https://github.com/EngineScript/free-gift-coupons-bulk-coupons-generator/pulls).
2. Create an issue for significant behavior changes.
3. Follow the existing code patterns in the relevant `includes/` class.

### Code Requirements

- **Input validation**: Validate all user input before use.
- **Output escaping**: Use `esc_html()`, `esc_attr()`, `esc_url()`, or another context-appropriate escaping function.
- **Sanitization**: Use WordPress sanitizers such as `sanitize_text_field()`, `sanitize_textarea_field()`, and `absint()`.
- **Nonce verification**: Use WordPress nonces for forms and state-changing requests.
- **Capability checks**: Verify permissions with `current_user_can()`.
- **PHPDoc**: Add `@param`, `@return`, and `@since` tags for new public functions and methods.

### Example Function

```php
/**
 * Example function with proper documentation.
 *
 * @since 1.6.0
 * @param string $input User input to process.
 * @return string Sanitized output.
 */
function fgcbg_example_function( string $input ): string {
    if ( ! current_user_can( 'manage_options' ) ) {
        return '';
    }

    $sanitized = sanitize_text_field( $input );

    return esc_html( $sanitized );
}
```

### Testing

1. **Manual testing**:

   - Test with WordPress 7.0 or higher.
   - Test with PHP 8.2 or higher.
   - Verify the generator screen at **WooCommerce > Coupon Generator**.
   - Verify product search, batch generation progress, and the generated code export.
   - Verify WooCommerce coupon creation and Free Gift Coupons metadata.

2. **Automated testing**:

   - Run PHPCS for coding standards.
   - Run PHPStan and Psalm for static analysis.
   - Run PHPMD for code quality checks.
   - Run PHPUnit tests.

## Submitting Changes

### Pull Request Process

1. Create a feature branch:

   ```bash
   git checkout -b feature/description-of-change
   ```

2. Make your changes:

   - Follow WordPress coding standards.
   - Add or update tests where useful.
   - Update documentation when behavior changes.

3. Run checks:

   ```bash
   composer run check-all
   ```

4. Commit your changes:

   ```bash
   git add .
   git commit -m "fix: preserve free gift coupon metadata"
   ```

5. Push and open a pull request:

   ```bash
   git push origin feature/description-of-change
   ```

### Commit Message Format

Use [Conventional Commits](https://conventionalcommits.org/):

- `feat:` New features
- `fix:` Bug fixes
- `docs:` Documentation changes
- `style:` Code style changes
- `refactor:` Code refactoring
- `test:` Test additions or changes
- `chore:` Maintenance tasks

## Pull Request Checklist

- [ ] Code follows WordPress coding standards
- [ ] Public functions and methods have useful PHPDoc
- [ ] Security best practices are implemented
- [ ] PHPCS, PHPStan, PHPMD, Psalm, and PHPUnit checks pass
- [ ] Manual testing is complete
- [ ] Documentation is updated if needed
- [ ] CHANGELOG.md and readme.txt are updated if behavior changes

## Version Management

When releasing new versions, update these files:

- `free-gift-bulk-coupon-generator.php` plugin header and `FGCBG_PLUGIN_VERSION`
- `includes/` version-dependent UI copy if needed
- `README.md`
- `readme.txt`
- `CHANGELOG.md`
- `languages/free-gift-bulk-coupon-generator.pot`

This project follows [Semantic Versioning](https://semver.org/):

- **MAJOR**: Breaking changes
- **MINOR**: New features
- **PATCH**: Bug fixes

## Support Channels

- **Issues**: [GitHub Issues](https://github.com/EngineScript/free-gift-coupons-bulk-coupons-generator/issues)
- **Discussions**: [GitHub Discussions](https://github.com/EngineScript/free-gift-coupons-bulk-coupons-generator/discussions)
- **Security**: Report security issues privately through the [vulnerability report form](https://github.com/EngineScript/free-gift-coupons-bulk-coupons-generator/security/advisories/new); see [SECURITY.md](SECURITY.md)

## Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WordPress Security Guidelines](https://developer.wordpress.org/plugins/security/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer)
- [PHPStan](https://phpstan.org/)
- [WordPress Plugin Check](https://wordpress.org/plugins/plugin-check/)

## License

By contributing to Free Gift Coupons Bulk Coupon Generator, you agree that your contributions will be licensed under [GPL-3.0-or-later](LICENSE).
