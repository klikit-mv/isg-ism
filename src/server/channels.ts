import { emailChannel } from './mail';
import { registerChannel } from './notifications';
import { telegramChannel } from './telegram';

/** Outbound notification channels, registered once when the server starts. */
registerChannel(telegramChannel);
registerChannel(emailChannel);
