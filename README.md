# Laravel Schema Refresh

*Supports SQLite databases only for the moment*

This package will allow you to re-run all of your `migrations` without loosing all of your data.

The `php artisan db:refresh` command will:

- backup the current database
- create a new database
- run all of the migrations
- copy the data from the old database to new

## Caveats
If you make breaking changes to your schema, for instance add a new column to a table without a default value, the refresh will fail for the obvious reasons.

## Installation
Install via composer:

    composer require boxed-code/laravel-schema-refresh 
 
## Misc
Pull requests welcome 😀. License MIT.
 
