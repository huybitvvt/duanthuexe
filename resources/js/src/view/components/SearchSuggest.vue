<template>
  <div class="search-suggest" @keydown="onKeydown" @focusout="onFocusOut">
    <el-input ref="input" v-bind="$attrs" :value="value" role="combobox"
      aria-autocomplete="list" :aria-expanded="String(open)" :aria-controls="listId"
      :aria-activedescendant="active >= 0 ? listId + '-' + active : null"
      @input="onInput" @focus="schedule" @change="$emit('change', $event)" @clear="$emit('clear')" />
    <div v-if="open" class="search-suggest-menu">
      <div v-if="loading || message" class="search-suggest-status" role="status">
        {{ loading ? 'Đang tìm…' : message }}
      </div>
      <ul :id="listId" role="listbox" :aria-label="$attrs.placeholder || 'Gợi ý tìm kiếm'">
        <li v-for="(item, index) in items" :id="listId + '-' + index" :key="item.value"
          role="option" :aria-selected="String(active === index)" :class="{ active: active === index }"
          @mousedown.prevent @click="select(item)" @mouseenter="active = index">{{ item.value }}</li>
      </ul>
    </div>
  </div>
</template>

<script>
import ApiService from "@/core/services/api.service";
import { buildSuggestions } from "@/core/services/search-suggestions";

export default {
  name: "SearchSuggest",
  inheritAttrs: false,
  props: {
    value: { type: [String, Number], default: "" },
    endpoint: { type: String, required: true },
    params: { type: Object, default: () => ({}) },
    queryKey: { type: String, default: "keyword" },
    fields: { type: String, required: true }
  },
  data() {
    return { open: false, loading: false, items: [], active: -1, message: "", timer: null, version: 0, listId: `search-suggestions-${this._uid}` };
  },
  watch: {
    params: { deep: true, handler() { if (this.open) this.schedule(); } },
    endpoint() { this.close(); }
  },
  beforeDestroy() { this.close(); },
  methods: {
    close() {
      clearTimeout(this.timer);
      this.version++;
      this.open = false;
      this.loading = false;
      this.items = [];
      this.active = -1;
    },
    onInput(value) {
      this.$emit("input", value);
      this.schedule(value);
    },
    schedule(input) {
      const keyword = (typeof input === "string" ? input : String(this.value || "")).trim();
      this.close();
      if (!keyword || !this.endpoint) return;
      this.open = true;
      this.loading = true;
      this.message = "";
      const version = this.version;
      this.timer = setTimeout(async () => {
        try {
          const params = { ...this.params, [this.queryKey]: keyword, page: 1, per_page: 20 };
          delete params.is_all;
          const { data } = await ApiService.query(this.endpoint, params);
          if (version !== this.version) return;
          this.items = buildSuggestions(data, this.fields, keyword);
          this.message = this.items.length ? "" : "Không có gợi ý phù hợp";
        } catch (error) {
          if (version !== this.version) return;
          this.message = "Không tải được gợi ý. Bạn vẫn có thể nhập và tìm kiếm.";
        } finally {
          if (version === this.version) this.loading = false;
        }
      }, 250);
    },
    select(item) {
      this.close();
      this.$emit("input", item.value);
      this.$emit("change", item.value);
      this.$nextTick(() => this.$emit("select", item));
    },
    onKeydown(event) {
      if (event.isComposing || event.keyCode === 229) return;
      if (event.key === "Escape") { this.close(); return; }
      if (event.key === "ArrowDown" || event.key === "ArrowUp") {
        if (!this.open || !this.items.length) return;
        event.preventDefault();
        this.active = (this.active + (event.key === "ArrowDown" ? 1 : -1) + this.items.length) % this.items.length;
        this.$nextTick(() => {
          const option = document.getElementById(this.listId + "-" + this.active);
          if (option) option.scrollIntoView({ block: "nearest" });
        });
      }
      if (event.key === "Enter") {
        event.preventDefault();
        if (this.open && this.active >= 0) this.select(this.items[this.active]);
        else { this.close(); this.$emit("submit"); }
      }
    },
    onFocusOut(event) {
      if (!event.relatedTarget || !this.$el.contains(event.relatedTarget)) this.close();
    }
  }
};
</script>

<style scoped>
.search-suggest { position: relative; width: 100%; }
.search-suggest-menu { position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 2100; background: #fff; border: 1px solid #dcdfe6; border-radius: 6px; box-shadow: 0 6px 18px rgba(0,0,0,.12); max-height: 280px; overflow-y: auto; }
.search-suggest-menu ul { list-style: none; padding: 4px 0; margin: 0; }
.search-suggest-menu li { padding: 10px 14px; cursor: pointer; color: #303133; overflow-wrap: anywhere; }
.search-suggest-menu li.active, .search-suggest-menu li:hover { background: #f1f5ff; color: #2455a4; }
.search-suggest-status { padding: 10px 14px; color: #606266; font-size: 13px; }
</style>
