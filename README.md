# Ewll/DBBundle
## Migrations
Create class `App\Migration\MigrationYmdHis` implementing `Ewll\DBBundle\Migration\MigrationInterface`.  
Put your migration up sql code into `::up()` method migrationd down sql code into `::down()` method and description into `::getDescription()`.

## Commands
- `ewll:db:migrate` - List migrations.  
  - `--all` - Migrate all.  
  - `--up YmdHis` - Migrate up specific one.  
  - `--down YmdHis` - Migrate down specific one.  
- `ewll:db:entity-cache` - Create entity cache. Use it after entity creation\updation.  
