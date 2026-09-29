import { config } from 'dotenv';

// Same order as Next.js: .env.local wins over .env.
config({ path: '.env.local', quiet: true });
config({ path: '.env', quiet: true });
