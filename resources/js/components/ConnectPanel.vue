<template>
  <Card class="srt-connect">
    <h2 class="srt-connect__title">Подключите Лучи Поддержки</h2>

    <template v-if="!state.configured">
      <p class="srt-muted">
        Интеграция не настроена: задайте SUPPORTRAYS_BASE_URL и SUPPORTRAYS_PROJECT_UUID.
      </p>
    </template>

    <template v-else>
      <p class="srt-muted">
        Тикеты открываются от имени вашей учётной записи менеджера в Лучах Поддержки. Мы отправим код
        на <strong>{{ state.email }}</strong>.
      </p>

      <form v-if="!codeSent" class="srt-connect__form" @submit.prevent="requestCode">
        <DefaultButton type="submit" :disabled="pending">{{ pending ? 'Отправка…' : 'Получить код' }}</DefaultButton>
      </form>

      <form v-else class="srt-connect__form" @submit.prevent="verify">
        <label class="srt-field">
          <span class="srt-field__label">Код из письма</span>
          <input
            ref="code"
            v-model.trim="code"
            class="form-control form-input form-control-bordered srt-connect__code"
            inputmode="numeric"
            autocomplete="one-time-code"
            required
          />
        </label>
        <div class="srt-actions">
          <DefaultButton type="submit" :disabled="pending || !code">{{ pending ? 'Проверка…' : 'Подключить' }}</DefaultButton>
          <OutlineButton type="button" :disabled="pending" @click="requestCode">Отправить ещё раз</OutlineButton>
        </div>
        <p class="srt-muted srt-small">
          Письмо не пришло? Значит, в Лучах Поддержки нет менеджера с этой почтой — попросите владельца
          организации добавить вас.
        </p>
      </form>

      <p v-if="error" class="srt-error" role="alert">{{ error }}</p>
    </template>
  </Card>
</template>

<script>
import { connection, errorMessage } from '../api'

export default {
  props: {
    state: { type: Object, required: true },
  },

  emits: ['connected'],

  data() {
    return { codeSent: false, code: '', pending: false, error: '' }
  },

  methods: {
    async requestCode() {
      this.pending = true
      this.error = ''
      try {
        await connection.requestCode()
        this.codeSent = true
        this.$nextTick(() => this.$refs.code?.focus())
      } catch (error) {
        this.error = errorMessage(error, 'Не удалось отправить код')
      } finally {
        this.pending = false
      }
    },

    async verify() {
      this.pending = true
      this.error = ''
      try {
        const state = await connection.verify(this.code)
        Nova.success('Лучи Поддержки подключены')
        this.$emit('connected', state)
      } catch (error) {
        this.error = errorMessage(error, 'Неверный или просроченный код')
      } finally {
        this.pending = false
      }
    },
  },
}
</script>
