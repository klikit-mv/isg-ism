export async function register() {
  if (process.env.NEXT_RUNTIME !== 'nodejs') return;
  await import('./server/channels');
  // Set the database up on every start, whatever command the host uses to start the app.
  const { runBootstrap, explain } = await import('./server/bootstrap');
  try {
    await runBootstrap();
  } catch (error) {
    console.error('Database setup failed —', explain(error), `(host ${process.env.DB_HOST ?? 'default'}, database ${process.env.DB_DATABASE ?? 'default'}, user ${process.env.DB_USERNAME ?? 'default'})`);
  }
}
