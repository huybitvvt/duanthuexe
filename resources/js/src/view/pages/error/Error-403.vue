<template>
  <div class="d-flex flex-column flex-root">
    <div
      class="d-flex flex-row-fluid flex-column bgi-size-cover bgi-position-center bgi-no-repeat p-10 p-sm-30"
      :style="{
        backgroundImage: `url(${backgroundImage})`
      }"
    >
      <h1
        class="font-size-sm-100 font-weight-boldest text-danger mt-15"
        style="font-size: 140px; line-height: 1;"
      >
        403
      </h1>
      <p class="font-size-h2 font-weight-bolder text-dark-75 mt-4">
        Truy cập bị từ chối (Forbidden)
      </p>
      <p class="font-size-h4 font-weight-normal text-muted max-w-600px">
        Bạn không có quyền truy cập chức năng này hoặc dữ liệu thuộc cơ sở khác ngoài phạm vi được phân công.
      </p>
      <div class="mt-6">
        <router-link to="/dashboard" class="btn btn-primary font-weight-bold px-8 py-3 mr-3">
          Quay lại Trang chủ
        </router-link>
        <button type="button" @click="goBack" class="btn btn-secondary font-weight-bold px-8 py-3">
          Quay lại Trang trước
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "Error-403",
  computed: {
    backgroundImage() {
      return process.env.BASE_URL + "media/error/bg1.jpg";
    }
  },
  methods: {
    goBack() {
      this.$router.go(-1);
    }
  }
};
</script>
