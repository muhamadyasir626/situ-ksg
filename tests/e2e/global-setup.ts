import { execSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';

export default function globalSetup() {
  writeFileSync('database/e2e.sqlite', '');
  const run = (cmd: string) => execSync(`php artisan --env=e2e ${cmd} --force`, { stdio: 'inherit' });
  run('migrate:fresh --seed');
  run('migrate:rollback --path=database/migrations/2026_08_01_174659_seed_sample_data_2026.php');
  run('migrate');
}
