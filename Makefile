lint:
	php vendor/bin/php-cs-fixer fix --allow-risky=yes .

phpstan:
	php vendor/bin/phpstan

test:
	php vendor/bin/phpunit
