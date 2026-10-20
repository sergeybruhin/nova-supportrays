<template>
  <div class="srt">
    <Head title="Лучи Поддержки" />

    <div class="srt-header">
      <Heading>Лучи Поддержки</Heading>
      <div v-if="state?.connected" class="srt-header__aside">
        <span class="srt-muted srt-small">{{ state.connected_as }}</span>
        <a class="srt-link srt-small" :href="`${state.portal_url}/manager`" target="_blank" rel="noopener noreferrer">Открыть портал</a>
        <button class="srt-link srt-small" type="button" @click="disconnect">Отключить</button>
      </div>
    </div>

    <LoadingView :loading="state === null">
      <ConnectPanel v-if="state && !state.connected" :state="state" @connected="onConnected" />

      <template v-else-if="state">
        <nav class="srt-tabs" aria-label="Очереди">
          <button
            v-for="item in tabs"
            :key="item.value"
            type="button"
            class="srt-tabs__tab"
            :class="{ 'srt-tabs__tab--active': tab === item.value }"
            :aria-pressed="tab === item.value"
            @click="setTab(item.value)"
          >
            {{ item.label }}
            <span v-if="item.count !== undefined && item.count !== null" class="srt-tabs__count">{{ item.count }}</span>
          </button>
        </nav>

        <div class="srt-toolbar">
          <input
            v-model.trim="searchInput"
            class="form-control form-input form-control-bordered srt-toolbar__search"
            type="search"
            placeholder="Поиск по теме и описанию"
            aria-label="Поиск"
          />
          <select v-model="status" class="form-control form-input form-control-bordered srt-toolbar__filter" aria-label="Статус" @change="reload(1)">
            <option value="">Все активные</option>
            <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
          </select>
          <select v-model="priority" class="form-control form-input form-control-bordered srt-toolbar__filter" aria-label="Приоритет" @change="reload(1)">
            <option value="">Любой приоритет</option>
            <option v-for="(label, value) in priorityLabels" :key="value" :value="value">{{ label }}</option>
          </select>
          <label class="srt-check"><input v-model="breached" type="checkbox" @change="reload(1)" /> Просрочено</label>
        </div>

        <p v-if="error" class="srt-error" role="alert">{{ error }}</p>

        <Card class="srt-tableCard">
          <table class="srt-table">
            <thead>
              <tr>
                <th>Тикет</th>
                <th>Статус</th>
                <th>Приоритет</th>
                <th>Клиент</th>
                <th>Исполнитель</th>
                <th>Обновлён</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="ticket in tickets" :key="ticket.uuid">
                <td>
                  <Link class="srt-link srt-table__primary" :href="ticketUrl(ticket)">{{ ticket.reference }} · {{ ticket.subject }}</Link>
                  <span class="srt-table__secondary">
                    <span v-if="ticket.source" class="srt-badge">{{ ticket.source }}</span>
                    <span v-if="ticket.awaiting_reply && isActive(ticket)" class="srt-badge srt-badge--danger">ждёт ответа</span>
                  </span>
                </td>
                <td data-label="Статус"><span class="srt-badge" :class="{ 'srt-badge--success': !isActive(ticket) }">{{ statusLabels[ticket.status] }}</span></td>
                <td data-label="Приоритет"><span class="srt-badge" :class="{ 'srt-badge--danger': ticket.priority === 'urgent' || isBreached(ticket) }">{{ priorityLabels[ticket.priority] }}</span></td>
                <td data-label="Клиент">{{ ticket.client?.name || '—' }}</td>
                <td data-label="Исполнитель">{{ ticket.assignee?.name || '—' }}</td>
                <td data-label="Обновлён">{{ formatTimestamp(ticket.last_activity_at) }}</td>
              </tr>
              <tr v-if="!loading && !tickets.length">
                <td class="srt-table__empty" colspan="6">Тикетов нет</td>
              </tr>
            </tbody>
          </table>
        </Card>

        <nav v-if="meta && meta.last_page > 1" class="srt-pagination" aria-label="Страницы">
          <OutlineButton type="button" :disabled="meta.current_page <= 1" @click="reload(meta.current_page - 1)">Назад</OutlineButton>
          <span class="srt-muted">Страница {{ meta.current_page }} из {{ meta.last_page }}</span>
          <OutlineButton type="button" :disabled="meta.current_page >= meta.last_page" @click="reload(meta.current_page + 1)">Далее</OutlineButton>
        </nav>
      </template>
    </LoadingView>
  </div>
</template>

<script>
import ConnectPanel from '../components/ConnectPanel'
import { connection, errorMessage, NotConnectedError, sr } from '../api'
import { formatTimestamp, isActive, isBreached, PRIORITY_LABELS, STATUS_LABELS } from '../format'

export default {
  components: { ConnectPanel },

  data() {
    return {
      state: null,
      tab: 'awaiting',
      page: 1,
      status: '',
      priority: '',
      breached: false,
      searchInput: '',
      search: '',
      tickets: [],
      meta: null,
      stats: null,
      loading: false,
      error: '',
      statusLabels: STATUS_LABELS,
      priorityLabels: PRIORITY_LABELS,
    }
  },

  computed: {
    tabs() {
      const stats = this.stats || {}
      return [
        { value: 'awaiting', label: 'Ждут ответа', count: stats.awaiting_reply },
        { value: 'mine', label: 'Мои', count: stats.assigned_to_me },
        { value: 'unassigned', label: 'Без исполнителя', count: stats.unassigned },
        { value: 'all', label: 'Все активные', count: stats.active },
      ]
    },
  },

  watch: {
    // Typed search waits for a pause before querying.
    searchInput(value) {
      clearTimeout(this.typing)
      this.typing = setTimeout(() => {
        this.search = value
        this.reload(1)
      }, 300)
    },
  },

  async mounted() {
    this.state = await connection.show()
    if (this.state.connected) this.reload(1)

    // The inbox stays open for long stretches; a minute is fresh enough.
    this.poll = setInterval(() => {
      if (this.state?.connected && !this.loading) this.reload(this.page, { quiet: true })
    }, 60_000)
  },

  beforeUnmount() {
    clearInterval(this.poll)
    clearTimeout(this.typing)
  },

  methods: {
    formatTimestamp,
    isActive,
    isBreached,

    ticketUrl(ticket) {
      return Nova.url(`/supportrays/tickets/${ticket.uuid}`)
    },

    setTab(tab) {
      this.tab = tab
      this.reload(1)
    },

    query() {
      return {
        page: this.page,
        sort: 'last_activity_at',
        ...(this.status ? { 'status[]': this.status } : { active: 1 }),
        ...(this.tab === 'awaiting' ? { awaiting_reply: 1 } : {}),
        ...(this.tab === 'mine' ? { assignee: 'me' } : {}),
        ...(this.tab === 'unassigned' ? { assignee: 'none' } : {}),
        ...(this.priority ? { 'priority[]': this.priority } : {}),
        ...(this.breached ? { breached: 1 } : {}),
        ...(this.search ? { search: this.search } : {}),
      }
    },

    async reload(page, { quiet = false } = {}) {
      this.page = page
      this.loading = !quiet
      this.error = ''
      try {
        const [list, stats] = await Promise.all([
          sr('get', 'tickets', { params: this.query() }),
          sr('get', 'tickets/stats'),
        ])
        this.tickets = list.data
        this.meta = list.meta
        this.stats = stats.data
      } catch (error) {
        if (error instanceof NotConnectedError) {
          this.state = await connection.show()
          return
        }
        if (!quiet) this.error = errorMessage(error, 'Не удалось загрузить тикеты')
      } finally {
        this.loading = false
      }
    },

    onConnected(state) {
      this.state = state
      this.reload(1)
    },

    async disconnect() {
      if (!window.confirm('Отключить Лучи Поддержки в этой панели? Ключ останется действующим в Лучах Поддержки — отзовите его в профиле на портале.')) return
      this.state = await connection.disconnect()
      this.tickets = []
      this.stats = null
    },
  },
}
</script>
