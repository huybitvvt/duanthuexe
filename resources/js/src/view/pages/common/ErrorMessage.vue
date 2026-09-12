<template>
    <div class="fv-plugins-message-container">
        <div :data-field="field" data-validator="notEmpty" class="fv-help-block">
            {{ errors[0] }}
        </div>
    </div>
</template>

<script>
export default {
    name: "ErrorMessage",
    props: {
        errors: Array,
        field: {
            type: String,
            default: () => { return '' }
        }
    }
}
</script>

<style scoped>

</style>
