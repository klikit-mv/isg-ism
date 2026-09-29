import './env.mts';
import { getPool } from '../src/db';
import { seedDemo } from '../src/server/demo-seed';

await seedDemo((m) => console.log(m));
await getPool().end();
