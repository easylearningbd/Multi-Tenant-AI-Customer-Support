# Authentication and top-level access control

NeuralDesk uses one `users` table and Laravel's `web` guard. The `role` column separates Subscriber (`user`) access from Super Admin (`admin`) access. Workspace membership roles belong to the future tenancy layer and must not be stored in this column.

## Create the initial administrator locally

Set these values in the local `.env` file:

```env
ADMIN_SEED_NAME="Super Admin"
ADMIN_SEED_EMAIL="admin@example.com"
ADMIN_SEED_PASSWORD="replace-with-a-strong-local-password"
```

Run the migration and dedicated seeder:

```bash
php artisan migrate
php artisan db:seed --class=AdminUserSeeder
```

The seeder creates or updates the configured email, hashes the password, and assigns the `admin` role. It is safe to run repeatedly. When `ADMIN_SEED_PASSWORD` is empty, a `password` fallback is permitted only in `local` and `testing`; all other environments fail with a configuration error. Set an explicit password for normal local use.

Subscriber sign-in is available at `/login` and Super Admin sign-in at `/admin/login`. The two forms share Laravel's session guard but accept only the role assigned to their surface.
