import Inbox from './pages/Inbox'
import Ticket from './pages/Ticket'

Nova.booting(() => {
  Nova.inertia('NovaSupportRaysInbox', Inbox)
  Nova.inertia('NovaSupportRaysTicket', Ticket)
})
