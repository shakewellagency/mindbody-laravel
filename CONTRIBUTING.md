# Contributing to Mindbody Laravel Package

We love your input! We want to make contributing to this project as easy and transparent as possible, whether it's:

- Reporting a bug
- Discussing the current state of the code
- Submitting a fix
- Proposing new features
- Becoming a maintainer

## Development Process

We use GitHub to host code, to track issues and feature requests, as well as accept pull requests.

1. Fork the repo and create your branch from `main`.
2. If you've added code that should be tested, add tests.
3. If you've changed APIs, update the documentation.
4. Ensure the test suite passes.
5. Make sure your code lints.
6. Issue that pull request!

## Setting up the Development Environment

1. **Clone your fork:**
   ```bash
   git clone https://github.com/your-username/mindbody-laravel.git
   cd mindbody-laravel
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Set up testing environment:**
   ```bash
   cp .env.example .env.testing
   # Edit .env.testing with your test credentials
   ```

4. **Run tests to ensure everything works:**
   ```bash
   composer test
   ```

## Code Style

We use PHP CS Fixer to maintain code style consistency. Before submitting your PR, make sure to run:

```bash
composer format
```

Our coding standards follow:
- PSR-12 coding standard
- Strict types declaration for all PHP files
- Comprehensive docblocks for all public methods
- Meaningful variable and method names
- Single responsibility principle

## Testing

We maintain high test coverage for this package. When contributing:

1. **Write tests for new features:**
   ```bash
   # Unit tests go in tests/Unit/
   # Feature tests go in tests/Feature/
   ```

2. **Run the full test suite:**
   ```bash
   composer test
   ```

3. **Run tests with coverage:**
   ```bash
   composer test:coverage
   ```

4. **Test specific files:**
   ```bash
   vendor/bin/phpunit tests/Unit/Services/MindbodyClientTest.php
   ```

### Test Categories

- **Unit Tests**: Test individual classes and methods in isolation
- **Feature Tests**: Test complete workflows and integrations
- **Integration Tests**: Test interactions with external APIs (mocked)

### Writing Good Tests

1. Use descriptive test method names: `test_it_can_authenticate_user_with_valid_credentials`
2. Follow the Arrange-Act-Assert pattern
3. Mock external dependencies appropriately
4. Test both success and failure scenarios
5. Use factories for test data generation

## Documentation

When contributing new features:

1. Update the README.md with usage examples
2. Add docblocks to all public methods
3. Update the CHANGELOG.md
4. Consider adding examples to the documentation

## Pull Request Process

1. **Create a feature branch:**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes with clear, concise commits:**
   ```bash
   git commit -m "feat: add client search functionality"
   ```

3. **Push to your fork and submit a pull request**

4. **Ensure your PR:**
   - Has a clear description of what it does
   - Includes tests for new functionality
   - Updates documentation as needed
   - Passes all existing tests
   - Follows the established code style

### Commit Message Format

We follow conventional commits:

- `feat:` A new feature
- `fix:` A bug fix
- `docs:` Documentation only changes
- `style:` Code style changes (formatting, etc.)
- `refactor:` Code refactoring
- `test:` Adding or updating tests
- `chore:` Maintenance tasks

Examples:
```
feat: add webhook retry mechanism
fix: resolve authentication token expiration issue
docs: update API usage examples
test: add integration tests for appointment booking
```

## Issue Reporting

When filing an issue, make sure to answer these questions:

1. What version of PHP are you using?
2. What version of Laravel are you using?
3. What did you do?
4. What did you expect to see?
5. What did you see instead?

### Bug Reports

Great bug reports tend to have:

- A quick summary and/or background
- Steps to reproduce (be specific!)
- What you expected would happen
- What actually happens
- Notes (possibly including why you think this might be happening)

### Feature Requests

For feature requests:

- Explain the problem you're trying to solve
- Describe the solution you'd like
- Describe alternatives you've considered
- Include any additional context

## Compatibility

This package supports:

- **PHP**: 8.1, 8.2, 8.3
- **Laravel**: 9.x, 10.x, 11.x
- **Mindbody API**: v6

When contributing, ensure your changes maintain compatibility with all supported versions.

## Security Vulnerabilities

**DO NOT** open GitHub issues for security vulnerabilities. Instead, please review our security policy and follow the responsible disclosure process outlined there.

## Code of Conduct

### Our Pledge

We pledge to make participation in our project a harassment-free experience for everyone, regardless of age, body size, disability, ethnicity, gender identity and expression, level of experience, nationality, personal appearance, race, religion, or sexual identity and orientation.

### Our Standards

Examples of behavior that contributes to creating a positive environment include:

- Using welcoming and inclusive language
- Being respectful of differing viewpoints and experiences
- Gracefully accepting constructive criticism
- Focusing on what is best for the community
- Showing empathy towards other community members

## Getting Help

- **Documentation**: Check the README.md first
- **Issues**: Search existing issues before creating a new one
- **Discussions**: Use GitHub Discussions for questions and ideas
- **Email**: For private matters, contact the maintainers directly

## Recognition

Contributors will be recognized in the README.md and CHANGELOG.md. We appreciate all forms of contribution!

## License

By contributing, you agree that your contributions will be licensed under the MIT License.