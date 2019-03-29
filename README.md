# ScandiPWA migrations
Module is a helper to install data and schema patches for ScandiPWA Theme.

## Usage
Run `magento setup:upgrade` to apply all new migrations.

## Adding new migration
In order to create a data or schema patch you need to:
1. Create new migration script under 'Setup/Patch/Data'
2. In newly created file add mandatory methods 'apply', 'getDependencies', 'getAliases'
3. Bump module versions in 'composer.json' and 'module.xml'