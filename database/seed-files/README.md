# Database Seed Files

Place PachyBase seed files in this directory.

Each seed file must:

- use the `.php` extension;
- return an instance of `PachyBase\Database\Seeds\SeederInterface`;
- expose a unique `name()` string;
- keep its execution idempotent.

Seeders are intended for initial data such as default system settings, development bootstrap records, and local demo fixtures.
