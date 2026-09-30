import './env.mts';
import { getPool } from '../src/db';
import { explain, runBootstrap } from '../src/server/bootstrap';

/** Apply migrations and first-run setup by hand (the app also does this on every start). */
try {
  await runBootstrap();
} catch (error) {
  console.error('Database setup failed:', explain(error));
  process.exitCode = 1;
}
await getPool().end();
