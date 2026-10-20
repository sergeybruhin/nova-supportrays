<template>
  <div class="srt">
    <Head :title="ticket ? ticket.reference : 'Тикет'" />

    <Link class="srt-link srt-back" :href="inboxUrl">
      <svg width="16" height="16" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M224,128a8,8,0,0,1-8,8H59.31l58.35,58.34a8,8,0,0,1-11.32,11.32l-72-72a8,8,0,0,1,0-11.32l72-72a8,8,0,0,1,11.32,11.32L59.31,120H216A8,8,0,0,1,224,128Z" /></svg>
      Тикеты
    </Link>

    <LoadingView :loading="state === null || (state.connected && !ticket && !loadError)">
      <ConnectPanel v-if="state && !state.connected" :state="state" @connected="onConnected" />

      <p v-else-if="loadError" class="srt-error" role="alert">{{ loadError }}</p>

      <template v-else-if="ticket">
        <div class="srt-header">
          <div class="srt-header__main">
            <Heading>{{ ticket.reference }} · {{ ticket.subject }}</Heading>
            <p class="srt-muted srt-header__sub">
              {{ ticket.client?.name }} · {{ ticket.project.name }}
              <span v-if="ticket.source" class="srt-badge">{{ ticket.source }}</span>
              <span v-if="ticket.awaiting_reply && isActive(ticket)" class="srt-badge srt-badge--danger">ждёт ответа</span>
            </p>
          </div>
          <a class="srt-link srt-small" :href="`${state.portal_url}/manager/tickets/${ticket.uuid}`" target="_blank" rel="noopener noreferrer">Открыть в Лучах Поддержки</a>
        </div>

        <p v-if="error" class="srt-error" role="alert">{{ error }}</p>

        <div class="srt-ticket">
          <div class="srt-ticket__thread">
            <Card class="srt-message">
              <p class="srt-message__meta">{{ ticket.client?.name }} · {{ formatTimestamp(ticket.created_at) }}</p>
              <p class="srt-message__body">{{ ticket.description }}</p>
              <ul v-if="ticket.attachments?.length" class="srt-files">
                <li v-for="file in ticket.attachments" :key="file.uuid"><a class="srt-link" :href="attachmentUrl(file.uuid)">{{ file.original_name }}</a> <span class="srt-muted srt-small">{{ formatSize(file.size) }}</span></li>
              </ul>
            </Card>

            <Card
              v-for="message in messages"
              :key="message.uuid"
              class="srt-message"
              :class="{ 'srt-message--internal': message.is_internal, 'srt-message--staff': isStaff(message) && !message.is_internal }"
            >
              <p class="srt-message__meta">
                {{ message.author.name || 'Без имени' }}<span v-if="message.author.type === 'imported'"> (импорт)</span>
                · {{ formatTimestamp(message.created_at) }}
                <span v-if="message.is_internal"> · внутренняя заметка</span>
              </p>
              <p class="srt-message__body">{{ message.body }}</p>
              <ul v-if="message.attachments?.length" class="srt-files">
                <li v-for="file in message.attachments" :key="file.uuid"><a class="srt-link" :href="attachmentUrl(file.uuid)">{{ file.original_name }}</a> <span class="srt-muted srt-small">{{ formatSize(file.size) }}</span></li>
              </ul>
              <p
                v-if="message.delivery"
                class="srt-message__delivery"
                :class="{ 'srt-message__delivery--failed': message.delivery.status === 'failed' }"
              >
                <template v-if="message.delivery.status === 'sent'">Доставлено клиенту · {{ formatTimestamp(message.delivery.reported_at) }}</template>
                <template v-else>Не доставлено клиенту: {{ message.delivery.error || 'причина не указана' }}</template>
              </p>
            </Card>

            <form v-if="ticket.status !== 'closed'" class="srt-reply" @submit.prevent="reply">
              <textarea
                v-model="body"
                class="form-control form-input form-control-bordered srt-reply__body"
                rows="5"
                required
                :placeholder="isInternal ? 'Заметка для коллег — клиент её не увидит' : 'Ответ клиенту'"
              />
              <p v-if="!isInternal && ticket.client_notifications === 'integration'" class="srt-muted srt-small">
                Ответ доставит {{ ticket.source || 'интеграция' }}; письмо клиенту не отправляется.
              </p>
              <div class="srt-actions srt-actions--end">
                <label class="srt-check"><input v-model="isInternal" type="checkbox" /> Внутренняя заметка</label>
                <DefaultButton type="submit" :disabled="sending || !body.trim()">{{ sending ? 'Отправка…' : 'Отправить' }}</DefaultButton>
              </div>
            </form>
          </div>

          <aside class="srt-ticket__side">
            <Card class="srt-side">
              <label class="srt-field">
                <span class="srt-field__label">Статус</span>
                <select :value="ticket.status" class="form-control form-input form-control-bordered" @change="changeStatus($event.target.value)">
                  <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
                </select>
              </label>
              <label class="srt-field">
                <span class="srt-field__label">Приоритет</span>
                <select :value="ticket.priority" class="form-control form-input form-control-bordered" :disabled="ticket.status === 'closed'" @change="changePriority($event.target.value)">
                  <option v-for="(label, value) in priorityLabels" :key="value" :value="value">{{ label }}</option>
                </select>
              </label>
              <label class="srt-field">
                <span class="srt-field__label">Исполнитель</span>
                <select :value="ticket.assignee?.uuid ?? ''" class="form-control form-input form-control-bordered" :disabled="ticket.status === 'closed'" @change="assign($event.target.value)">
                  <option value="">Не назначен</option>
                  <option v-for="user in staff" :key="user.uuid" :value="user.uuid">{{ user.name }}</option>
                </select>
              </label>
              <dl v-if="ticket.sla" class="srt-list">
                <dt>Первый ответ до</dt>
                <dd :class="{ 'srt-late': ticket.sla.first_response_breached_at }">{{ ticket.first_response_at ? 'отвечено' : formatTimestamp(ticket.sla.first_response_due_at) }}</dd>
                <dt>Решение до</dt>
                <dd :class="{ 'srt-late': ticket.sla.resolution_breached_at }">{{ formatTimestamp(ticket.sla.resolution_due_at) }}</dd>
              </dl>
            </Card>

            <Card class="srt-side">
              <label class="srt-field">
                <span class="srt-field__label">Категория</span>
                <select :value="ticket.category?.uuid ?? ''" class="form-control form-input form-control-bordered" :disabled="ticket.status === 'closed'" @change="setCategory($event.target.value)">
                  <option value="">Без категории</option>
                  <option v-for="category in categories" :key="category.uuid" :value="category.uuid">{{ category.name }}</option>
                </select>
              </label>

              <div class="srt-field">
                <span class="srt-field__label">Теги</span>
                <div class="srt-chips">
                  <span v-for="tag in ticket.tags || []" :key="tag.uuid" class="srt-chip" :style="tag.color ? { borderColor: tag.color } : null">
                    {{ tag.name }}
                    <button v-if="ticket.status !== 'closed'" type="button" class="srt-chip__remove" :aria-label="`Убрать тег ${tag.name}`" @click="removeTag(tag.uuid)">×</button>
                  </span>
                  <span v-if="!ticket.tags?.length" class="srt-muted srt-small">нет</span>
                </div>
                <select v-if="availableTags.length && ticket.status !== 'closed'" class="form-control form-input form-control-bordered" value="" @change="addTag($event.target.value); $event.target.value = ''">
                  <option value="">Добавить тег…</option>
                  <option v-for="tag in availableTags" :key="tag.uuid" :value="tag.uuid">{{ tag.name }}</option>
                </select>
              </div>

              <div class="srt-field">
                <span class="srt-field__label">Наблюдатели</span>
                <div class="srt-chips">
                  <span v-for="watcher in ticket.watchers || []" :key="watcher.uuid" class="srt-chip">
                    {{ watcher.name }}
                    <button type="button" class="srt-chip__remove" :aria-label="`Убрать ${watcher.name}`" @click="removeWatcher(watcher.uuid)">×</button>
                  </span>
                  <span v-if="!ticket.watchers?.length" class="srt-muted srt-small">нет</span>
                </div>
                <select v-if="availableWatchers.length" class="form-control form-input form-control-bordered" value="" @change="addWatcher($event.target.value); $event.target.value = ''">
                  <option value="">Добавить наблюдателя…</option>
                  <option v-for="user in availableWatchers" :key="user.uuid" :value="user.uuid">{{ user.name }}</option>
                </select>
              </div>
            </Card>

            <Card v-for="section in context.sections" :key="section.title" class="srt-side">
              <h3 class="srt-side__title">{{ section.title }}</h3>
              <dl class="srt-list">
                <template v-for="(row, index) in section.rows" :key="index">
                  <dt>{{ row.label }}</dt>
                  <dd>
                    <Link v-if="row.url && isInternal(row.url)" class="srt-link" :href="row.url">{{ row.value ?? '—' }}</Link>
                    <a v-else-if="row.url && isSafeUrl(row.url)" class="srt-link" :href="row.url" target="_blank" rel="noopener noreferrer">{{ row.value ?? '—' }}</a>
                    <template v-else>{{ row.value ?? '—' }}</template>
                  </dd>
                </template>
              </dl>
            </Card>

            <Card v-if="client" class="srt-side">
              <h3 class="srt-side__title">Клиент</h3>
              <dl class="srt-list">
                <dt>Имя</dt><dd>{{ client.name }}</dd>
                <template v-if="client.email"><dt>Email</dt><dd>{{ client.email }}</dd></template>
                <template v-if="client.phone"><dt>Телефон</dt><dd>{{ client.phone }}</dd></template>
                <template v-for="(value, key) in client.metadata || {}" :key="key"><dt>{{ key }}</dt><dd>{{ value ?? '—' }}</dd></template>
              </dl>
            </Card>

            <Card v-if="ticket.custom_fields?.length" class="srt-side">
              <h3 class="srt-side__title">Данные магазина</h3>
              <dl class="srt-list">
                <template v-for="field in ticket.custom_fields" :key="field.key">
                  <dt>{{ field.label }}</dt>
                  <dd>
                    <a v-if="field.type === 'url' && isSafeUrl(field.value)" class="srt-link" :href="field.value" target="_blank" rel="noopener noreferrer">{{ field.value }}</a>
                    <span v-else-if="field.type === 'badge'" class="srt-badge">{{ formatCustomField(field) }}</span>
                    <template v-else>{{ formatCustomField(field) }}</template>
                  </dd>
                </template>
              </dl>
            </Card>

            <Card v-if="context.links.length || safeLinks.length" class="srt-side srt-side--links">
              <Link v-for="link in context.links" :key="link.url" class="srt-link" :href="link.url">{{ link.title }}</Link>
              <a v-for="link in safeLinks" :key="link.url" class="srt-link" :href="link.url" target="_blank" rel="noopener noreferrer">{{ link.title }}</a>
            </Card>

            <Card class="srt-side">
              <button class="srt-link srt-small" type="button" @click="toggleActivity">{{ showActivity ? 'Скрыть историю' : 'История изменений' }}</button>
              <ol v-if="showActivity && activity" class="srt-activity">
                <li v-for="(entry, index) in activity" :key="index">
                  <span>{{ entry.description }}</span>
                  <span class="srt-muted srt-small">
                    {{ entry.causer?.name || 'Система' }}<template v-if="entry.properties?.via"> (через {{ entry.properties.via }})</template>
                    · {{ formatTimestamp(entry.created_at) }}
                  </span>
                </li>
              </ol>
            </Card>
          </aside>
        </div>
      </template>
    </LoadingView>
  </div>
</template>

<script>
import ConnectPanel from '../components/ConnectPanel'
import { attachmentUrl, connection, errorMessage, newIdempotencyKey, NotConnectedError, sr, ticketContext } from '../api'
import { formatCustomField, formatSize, formatTimestamp, isActive, isSafeUrl, PRIORITY_LABELS, STATUS_LABELS } from '../format'

export default {
  components: { ConnectPanel },

  props: {
    uuid: { type: String, required: true },
  },

  data() {
    return {
      state: null,
      ticket: null,
      messages: [],
      users: [],
      tags: [],
      categories: [],
      context: { sections: [], links: [] },
      client: null,
      body: '',
      isInternal: false,
      sending: false,
      error: '',
      loadError: '',
      showActivity: false,
      activity: null,
      statusLabels: STATUS_LABELS,
      priorityLabels: PRIORITY_LABELS,
    }
  },

  computed: {
    inboxUrl() {
      return Nova.url('/supportrays')
    },

    staff() {
      return this.users.filter(user => user.is_active)
    },

    safeLinks() {
      // The host's own Nova links already cover what an integration pushed
      // for its Nova; drop the duplicates.
      const own = new Set(this.context.links.map(link => link.title))
      return (this.ticket?.links || []).filter(link => isSafeUrl(link.url) && !own.has(link.title))
    },

    availableTags() {
      const current = new Set((this.ticket?.tags || []).map(tag => tag.uuid))
      return this.tags.filter(tag => !current.has(tag.uuid))
    },

    availableWatchers() {
      const current = new Set((this.ticket?.watchers || []).map(watcher => watcher.uuid))
      return this.staff.filter(user => !current.has(user.uuid))
    },
  },

  async mounted() {
    this.state = await connection.show()
    if (this.state.connected) await this.load()

    // New replies arrive from the client and from the shop; keep the open
    // thread current without a manual reload.
    this.poll = setInterval(() => {
      if (this.state?.connected && this.ticket && !this.sending) this.refresh()
    }, 20_000)
  },

  beforeUnmount() {
    clearInterval(this.poll)
  },

  methods: {
    attachmentUrl,
    formatCustomField,
    formatSize,
    formatTimestamp,
    isActive,
    isSafeUrl,

    /** Host links are Nova paths; anything else opens in a new tab. */
    isInternal(url) {
      return url.startsWith('/') && !url.startsWith('//')
    },

    isStaff(message) {
      return message.author.type === 'organization_user' || message.author.type === 'admin'
    },

    /** Runs a call; a lost connection swaps the page for the connect screen. */
    async guard(action, fallback) {
      try {
        return await action()
      } catch (error) {
        if (error instanceof NotConnectedError) {
          this.state = await connection.show()
          return undefined
        }
        this.error = errorMessage(error, fallback)
        return undefined
      }
    },

    async load() {
      this.loadError = ''
      try {
        const [ticket, messages, users] = await Promise.all([
          sr('get', `tickets/${this.uuid}`),
          sr('get', `tickets/${this.uuid}/messages`, { params: { per_page: 100 } }),
          sr('get', 'users', { params: { per_page: 100 } }),
        ])
        this.ticket = ticket.data
        this.messages = messages.data
        this.users = users.data
      } catch (error) {
        if (error instanceof NotConnectedError) {
          this.state = await connection.show()
          return
        }
        this.loadError = error?.response?.status === 404 ? 'Тикет не найден' : errorMessage(error, 'Не удалось открыть тикет')
        return
      }

      // Side data: none of it is worth failing the page over.
      const quiet = action => action().catch(error => {
        if (error instanceof NotConnectedError) throw error
        return null
      })
      try {
        const [client, tags, categories, context] = await Promise.all([
          // The ticket carries only the client's name; the card needs the rest.
          this.ticket.client?.uuid ? quiet(() => sr('get', `clients/${this.ticket.client.uuid}`)) : null,
          quiet(() => sr('get', 'tags')),
          quiet(() => sr('get', `projects/${this.ticket.project.uuid}/categories`)),
          quiet(() => ticketContext(this.uuid)),
        ])
        this.client = client?.data ?? null
        this.tags = tags?.data ?? []
        this.categories = categories?.data ?? []
        this.context = { sections: context?.sections ?? [], links: context?.links ?? [] }
      } catch {
        this.state = await connection.show()
      }
    },

    async refresh() {
      const [ticket, messages] = await Promise.all([
        this.guard(() => sr('get', `tickets/${this.uuid}`), 'Не удалось обновить тикет'),
        this.guard(() => sr('get', `tickets/${this.uuid}/messages`, { params: { per_page: 100 } }), 'Не удалось обновить сообщения'),
      ])
      if (ticket) this.ticket = ticket.data
      if (messages) this.messages = messages.data
      if (this.showActivity) await this.loadActivity()
    },

    async reply() {
      this.sending = true
      this.error = ''
      const done = await this.guard(
        () => sr('post', `tickets/${this.uuid}/messages`, {
          data: { body: this.body, is_internal: this.isInternal },
          idempotencyKey: newIdempotencyKey(),
        }),
        'Не удалось отправить сообщение',
      )
      if (done) {
        this.body = ''
        this.isInternal = false
        await this.refresh()
      }
      this.sending = false
    },

    async act(action, fallback) {
      this.error = ''
      await this.guard(action, fallback)
      await this.refresh()
    },

    changeStatus(status) {
      return this.act(() => sr('post', `tickets/${this.uuid}/status`, { data: { status } }), 'Не удалось изменить статус')
    },

    changePriority(priority) {
      return this.act(() => sr('patch', `tickets/${this.uuid}`, { data: { priority } }), 'Не удалось изменить приоритет')
    },

    assign(assigneeUuid) {
      return this.act(() => sr('post', `tickets/${this.uuid}/assign`, { data: { assignee_uuid: assigneeUuid || null } }), 'Не удалось назначить исполнителя')
    },

    setCategory(categoryUuid) {
      return this.act(() => sr('patch', `tickets/${this.uuid}`, { data: { category_uuid: categoryUuid || null } }), 'Не удалось изменить категорию')
    },

    saveTags(tagUuids) {
      return this.act(() => sr('patch', `tickets/${this.uuid}`, { data: { tag_uuids: tagUuids } }), 'Не удалось изменить теги')
    },

    addTag(tagUuid) {
      if (!tagUuid) return undefined
      return this.saveTags([...(this.ticket.tags || []).map(tag => tag.uuid), tagUuid])
    },

    removeTag(tagUuid) {
      return this.saveTags((this.ticket.tags || []).map(tag => tag.uuid).filter(uuid => uuid !== tagUuid))
    },

    addWatcher(userUuid) {
      if (!userUuid) return undefined
      return this.act(() => sr('post', `tickets/${this.uuid}/watchers`, { data: { organization_user_uuid: userUuid } }), 'Не удалось добавить наблюдателя')
    },

    removeWatcher(userUuid) {
      return this.act(() => sr('delete', `tickets/${this.uuid}/watchers/${userUuid}`), 'Не удалось убрать наблюдателя')
    },

    async loadActivity() {
      const response = await this.guard(() => sr('get', `tickets/${this.uuid}/activity`, { params: { per_page: 50 } }), 'Не удалось загрузить историю')
      if (response) this.activity = response.data
    },

    async toggleActivity() {
      this.showActivity = !this.showActivity
      if (this.showActivity && !this.activity) await this.loadActivity()
    },

    onConnected(state) {
      this.state = state
      this.load()
    },
  },
}
</script>
