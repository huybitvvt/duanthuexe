<template>
    <div v-loading="loadingComponent">
        <ValidationObserver v-slot="{ handleSubmit }" ref="form">
            <form class="form" @submit.prevent="handleSubmit(handleFormSubmit)">
				
				<div class="row mb-4 align-items-center">
					<div class="col-md-6">
						<div class="d-flex justify-content-start align-items-center flex-wrap">
							<h6 v-if="id" class="mb-0 mr-3">ID hợp đồng: #{{ id }}</h6>
							<div class="d-inline-flex align-items-center">
								<span class="badge badge-primary px-3 py-2" style="font-size: 13px;">
									Số HĐ: <strong>{{ order.contract_number || '(Hệ thống tự cấp khi lưu đơn)' }}</strong>
								</span>
								<span v-if="id && is_deposit_contract_mode" class="font-weight-bold badge badge-success ml-2">Cọc giữ xe</span>
								<span v-if="order && (order.contract_is_locked || (order.contract_snapshot && order.contract_snapshot.is_locked))" class="font-weight-bold badge badge-warning ml-2" style="font-size: 12px;">
									Hợp đồng đã chốt
								</span>
							</div>
						</div>

						<div class="form-group mt-2 mb-0" v-if="id && order">
							<label class="mb-0">
								<strong>Ngày tạo hợp đồng</strong>
								<span v-if="!editing_order_created_at">: &nbsp;{{ order.created_at }}</span>
								<button type="button" class="btn btn-sm btn-link py-0 px-1 font-weight-bold" @click="editingOrderCreatedAt">[Sửa]</button>
							</label>
							<ValidationProvider v-if="editing_order_created_at" vid="completed_at" name="Ngày tạo hợp đồng" rules="required" v-slot="{ errors }">
								<el-date-picker class="w-100" v-model="order.created_at" format="dd-MM-yyyy HH:mm:ss" type="datetime" placeholder="Ngày tạo hợp đồng"></el-date-picker>
								<error-message :errors="errors" field="completed_at"></error-message>
							</ValidationProvider>
						</div>
					</div>
					<div class="col-md-6 text-right">
						<div class="d-flex justify-content-end align-items-center" v-if="id && order && order.order_status == 'deposit_contract'">
							<div class="checkbox-wrapper deposit-contract-checkbox">
								<input type="checkbox" class="checkbox-input" v-model="start_this_contract" id="start-this-contract">
								<label for="start-this-contract" class="mb-0 font-weight-bold text-success">Kích hoạt hợp đồng này (Cấp Số HĐ chính thức)</label>
							</div>
						</div>
						<div class="d-flex justify-content-end align-items-center" v-else-if="id && order && order.order_status == 'renting' && !(order.contract_is_locked || (order.contract_snapshot && order.contract_snapshot.is_locked))">
							<button type="button" class="btn btn-sm btn-outline-warning font-weight-bold" @click="handleLockContract" :disabled="loadingLock">
								Chốt hợp đồng đã ký
							</button>
						</div>
					</div>
				</div>

				<div class="card card-custom gutter-b border p-4 bg-light-secondary mb-6">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<h5 class="font-weight-bold text-primary mb-0">Thông tin hợp đồng & Pháp lý</h5>
						<div class="checkbox-wrapper d-flex align-items-center">
							<input type="checkbox" class="checkbox-input mr-2" v-model="order.is_authorized_contract" id="is-authorized-contract">
							<label for="is-authorized-contract" class="mb-0 font-weight-bold">Hợp đồng theo ủy quyền</label>
						</div>
					</div>
					<div class="row">
						<div class="col-md-4 form-group" v-if="id == 0 || id == null">
							<label for="payment-method"><strong>Loại hợp đồng<span class="text-danger">(*)</span></strong></label>
							<div class="deposit-contract-checkbox checkbox-wrapper">
								<el-radio-group id="payment-method" v-model="contract_type" size="medium">
									<el-radio-button label="1">Thuê xe</el-radio-button>
									<el-radio-button label="2">Đặt cọc giữ xe</el-radio-button>
								</el-radio-group>
							</div>
						</div>
						<div class="col-md-4 form-group">
							<label><strong>Ngày ký hợp đồng<span class="text-danger">(*)</span></strong></label>
							<el-date-picker class="w-100" v-model="order.contract_signed_on" format="dd-MM-yyyy" value-format="yyyy-MM-dd" type="date" placeholder="Chọn ngày ký"></el-date-picker>
						</div>
						<div class="col-md-4 form-group" v-if="id == 0 || id == null">
							<label><strong>Ngày tạo hợp đồng<span class="text-danger">(*)</span></strong></label>
							<ValidationProvider vid="completed_at" name="Ngày tạo hợp đồng" rules="required" v-slot="{ errors }">
								<el-date-picker class="w-100" v-model="order.created_at" format="dd-MM-yyyy HH:mm:ss" type="datetime" placeholder="Ngày tạo hợp đồng"></el-date-picker>
								<error-message :errors="errors" field="completed_at"></error-message>
							</ValidationProvider>
						</div>
						<div class="col-md-6 form-group" v-if="order.is_authorized_contract">
							<label><strong>Ngày HĐ ủy quyền</strong></label>
							<el-date-picker class="w-100" v-model="order.contract_authorization_date" format="dd-MM-yyyy" value-format="yyyy-MM-dd" type="date" placeholder="Ngày HĐ ủy quyền"></el-date-picker>
						</div>
						<div class="col-md-6 form-group" v-if="order.is_authorized_contract">
							<label><strong>Bên được ủy quyền</strong></label>
							<el-input placeholder="Tên đơn vị / cá nhân được ủy quyền" v-model="order.contract_authorization_party_name"></el-input>
						</div>
					</div>
				</div>

                <div class="d-flex justify-content-center mb-6">
                    <h2 class="font-weight-bold">Thông tin khách hàng (Bên B)</h2>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><strong>Cửa hàng xe</strong> <span class="text-danger">(*)</span></label>
                            <ValidationProvider vid="store_id" name="Cửa hàng xe" rules="required" v-slot="{ errors }">
                                <el-select name="store_id" v-model="order.store_id" clearable filterable class="w-100"
                                    placeholder="Chọn cửa hàng" @change="onStoreChange($event)">
                                    <el-option v-for="item in stores" :key="item.name" :label="item.store_name"
                                        :value="item.id">
                                    </el-option>
                                </el-select>
                                <error-message :errors="errors" field="store_id"></error-message>
                            </ValidationProvider>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><strong>Tên khách hàng</strong> <span class="text-danger">(*)</span></label>
                            <ValidationProvider vid="name" name="Tên khách hàng" rules="required" v-slot="{ errors }">
                                <el-input placeholder="Tên khách hàng" v-model="order.customer_name"></el-input>
                                <error-message :errors="errors" field="name"></error-message>
                            </ValidationProvider>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><strong>SĐT</strong> <span class="text-danger">(*)</span></label>
                            <ValidationProvider vid="phone" name="Số điện thoại khách hàng" rules="required|numeric"
                                v-slot="{ errors }">
                                <el-input clearable placeholder="SĐT khách hàng" v-model="order.customer_phone"
                                    @blur="onBlurCardId($event, errors)" @change="onChangeCardId($event)"
                                    name="phone"></el-input>
                                <error-message :errors="errors" field="phone"></error-message>
                            </ValidationProvider>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label><strong>Số CMTND/CCCD</strong> <span class="text-danger">(*)</span></label>
                            <ValidationProvider vid="cccd" name="Số CMTND/CCCD" rules="required|numeric"
                                v-slot="{ errors }">
                                <el-input clearable placeholder="Số CMTND/CCCD" v-model="order.customer_id_card"
                                    @blur="onBlurCardId($event, errors)" @change="onChangeCardId($event)"
                                    name="id_card"></el-input>
                                <error-message :errors="errors" field="cccd"></error-message>
                            </ValidationProvider>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><strong>Ngày cấp CCCD</strong></label>
                            <el-date-picker class="w-100" v-model="order.customer_id_card_issued_on" format="dd-MM-yyyy" value-format="yyyy-MM-dd" type="date" placeholder="Ngày cấp CCCD"></el-date-picker>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><strong>Nơi cấp CCCD</strong></label>
                            <el-select class="w-100" filterable clearable placeholder="Chọn nơi cấp CCCD" v-model="order.customer_id_card_issued_by">
                                <el-option label="CỤC TRƯỞNG CỤC CẢNH SÁT QUẢN LÝ HÀNH CHÍNH VỀ TRẬT TỰ XÃ HỘI" value="CỤC TRƯỞNG CỤC CẢNH SÁT QUẢN LÝ HÀNH CHÍNH VỀ TRẬT TỰ XÃ HỘI"></el-option>
                                <el-option label="CỤC TRƯỞNG CỤC CẢNH SÁT ĐKQL CƯ TRÚ VÀ DLQG VỀ DÂN CƯ" value="CỤC TRƯỞNG CỤC CẢNH SÁT ĐKQL CƯ TRÚ VÀ DLQG VỀ DÂN CƯ"></el-option>
                                <el-option label="BỘ CÔNG AN" value="BỘ CÔNG AN"></el-option>
                            </el-select>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label><strong>Địa chỉ thường trú / tạm trú</strong></label>
                            <ValidationProvider vid="customer_address" name="Địa chỉ" rules="" v-slot="{ errors }">
                                <el-input clearable placeholder="Địa chỉ nơi cư trú của khách hàng"
                                    v-model="order.customer_address"></el-input>
                                <error-message :errors="errors" field="customer_address"></error-message>
                            </ValidationProvider>
                        </div>
                    </div>

                    <!-- Thông tin người thân (theo mẫu: ... Và ...) -->
                    <div class="col-md-12" v-if="order.relatives && order.relatives.length">
                        <div class="p-3 mb-4 rounded" style="background-color: #f7f9fb; border: 1px solid #e1e8ed;">
                            <label class="font-weight-bold text-dark mb-2">
                                Thông tin người thân (theo mẫu HĐ: ... Và ...):
                            </label>
                            <div class="row mb-2">
                                <div class="col-md-4">
                                    <el-input size="small" placeholder="Người thân 1: Họ tên" v-model="order.relatives[0].name"></el-input>
                                </div>
                                <div class="col-md-4">
                                    <el-input size="small" placeholder="Mối quan hệ (bố, mẹ, vợ, chồng...)" v-model="order.relatives[0].relationship"></el-input>
                                </div>
                                <div class="col-md-4">
                                    <el-input size="small" placeholder="SĐT người thân 1" v-model="order.relatives[0].phone"></el-input>
                                </div>
                            </div>
                            <div class="d-flex align-items-center my-2 text-muted font-weight-bold" style="font-size: 12px;" v-if="order.relatives.length > 1">
                                <span class="badge badge-secondary mr-2">Và</span> (Người thân thứ 2):
                            </div>
                            <div class="row" v-if="order.relatives.length > 1">
                                <div class="col-md-4">
                                    <el-input size="small" placeholder="Người thân 2: Họ tên" v-model="order.relatives[1].name"></el-input>
                                </div>
                                <div class="col-md-4">
                                    <el-input size="small" placeholder="Mối quan hệ" v-model="order.relatives[1].relationship"></el-input>
                                </div>
                                <div class="col-md-4">
                                    <el-input size="small" placeholder="SĐT người thân 2" v-model="order.relatives[1].phone"></el-input>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="leads.length > 0">
                    <div><strong>Lead:</strong></div>
                    <div v-for="lead in leads">
                        <span v-if="lead.customer_phone"> {{ lead.customer_phone }}</span>
                        <span v-if="lead.customer_name"> - {{ lead.customer_name }}</span>
                        <span v-if="lead.vehicle_name"> - {{ lead.vehicle_name }}</span>
                        <span v-if="lead.store_name"> - {{ lead.store_name }}</span>
                        <span v-if="lead.rent_at"> - Thuê từ ngày {{ lead.rent_at }}</span>
                        <span v-if="lead.return_at"> - Đến ngày {{ lead.return_at }}</span>
                    </div>

                </div>
                <el-alert v-if="!!warningTemp" title="Cảnh báo" :description="warningTemp" type="error" effect="dark"
                    :closable="false">
                </el-alert>

                <div v-show="!warningTemp">
                    <el-divider></el-divider>

                    <div class="d-flex justify-content-center mb-6">
                        <h2 class="font-weight-bold">Thông tin phương tiện</h2>
                    </div>

                    <div class="mb-3 d-flex flex-grow-1 align-items-center p-2 rounded">
                        <div class="mr-4 flex-shrink-0">
                            <button :style="{
                                'pointer-events': order.order_status === 'completed' ? 'none' : 'auto'
                            }" class="btn btn-sm btn-outline-success font-weight-bold" @click="addVehicle()">
                                Thêm phương tiện
                            </button>
                        </div>
                    </div>
                    <div v-if="order.order_items" v-for="(item, key) in order.order_items" :key="key">
                        <items-order :priceVehicles="priceVehicles" :order_item="item" :banks="banks"
							:is_deposit_contract_mode="is_deposit_contract_mode"
                            :ref="'itemOrder-' + key" :vehicles="vehicles" :index="key" :order_id="id"
                            :customer_name="order.customer_name"
                            @deleteFee="deleteFee" @deleteVehicle="deleteVehicle" @changeRentAt="changeRentAt" @addFee="addFee" @feeChanged="feeChanged"
                            @changeReturnAt="changeReturnAt" @changeIsAllInOne="changeIsAllInOne"
                            @changeBorrowHats="changeBorrowHats" @changeVehicleId="changeVehicleId"
                            @changeHandlerPrice="changeHandlerPrice" :other_fee_bank_id="order.other_fee_bank_id"
                            :other_fee_payment_method="order.other_fee_payment_method" 
							@other_fee_bank_id="changeOtherFeeBankId" @odometerAfterChanged="odometerAfterChanged" @odometerBeforeChanged="odometerBeforeChanged"
                            @change_substitute_unit_price="change_substitute_unit_price"
                            @change_money_out_date="change_money_out_date"
                            @changeDriverName="changeDriverName"
                            @changeDriverLicenseNumber="changeDriverLicenseNumber"
                            @changeDriverLicenseIssuedOn="changeDriverLicenseIssuedOn"
                            @changeBorrowRaincoats="changeBorrowRaincoats"
                            @other_fee_payment_method="changeOtherFeePaymentMethod" :order_status="order.order_status"
							@item_hiring_fee_changed="item_hiring_fee_changed">
                        </items-order>
                    </div>
                    <div class="d-flex justify-content-center mb-6 mt-6 mb-10">
                        <h2 class="font-weight-bold">Chi phí</h2>
                    </div>

					<div class="row">
						<div class="col-md-9 left-column">

							<div class="row" :class="order?.created_without_collect_deposit ? 'd-none' : ''">
								<div class="form-group col-md-12">
									<h5 class="mr-5"><strong>Thu tiền cọc</strong></h5>
									<div class="checkbox-wrapper mt-2" v-if="(id == 0 || id == null) && contract_type == 1">
										<input type="checkbox" class="checkbox-input" v-model="order.create_order_without_input_deposit" id="create_order_without_input_deposit">
										<label for="create_order_without_input_deposit" class="mb-0"><strong>Tạo hợp đồng mà không thu cọc</strong></label>
									</div>
								</div>
							</div>

			<div class="row mb-10" v-if="!order.create_order_without_input_deposit" :class="order?.created_without_collect_deposit ? 'd-none' : ''">
								<div class="col-12">
									<PaymentMethod label="Hình thức thu cọc" :settings="order.first_deposit_payment_method" :banks="banks" :fixedAmount="firstDepositValInput" @setting_changed="order_first_deposit_changed" class="contract-payment-method">
										<template #amount>
											<div class="form-group col-md-3">
									<label for="paid" v-if="start_this_contract"><strong>Đã thu cọc giữ xe</strong></label>
									<label for="paid" v-else-if="is_deposit_contract_mode"><strong>Thu cọc giữ xe</strong></label>
									<label for="paid" v-else><strong>Số tiền đặt cọc</strong></label>

									<ValidationProvider name="Số tiền đặt cọc" rules="min_value:0" mode="lazy" v-slot="{ errors }" vid="amount">
										<money id="paid" v-model="firstDepositValInput" v-bind="money" class="form-control" :disabled="start_this_contract"></money>
										<error-message :errors="errors" field="amount"></error-message>
									</ValidationProvider>
								</div>
										</template>
									</PaymentMethod>
								</div>
							</div>

							<div v-if="start_this_contract"><hr/></div>

							<div class="row mb-10" v-if="start_this_contract || (id && order.additional_deposit_amount)">
								<div class="col-12">
									<PaymentMethod label="Hình thức thu thêm cọc" :settings="order.additional_deposit_payment_method" :banks="banks" :fixedAmount="order.additional_deposit_amount" @setting_changed="additional_deposit_amount_changed" class="contract-payment-method">
										<template #amount>
											<div class="col-md-3 form-group">
									<label for="paid"><strong>Thu thêm cọc</strong></label>
									<ValidationProvider name="Thu thêm" mode="lazy" v-slot="{ errors }" vid="additional_deposit_amount">
										<money id="additional_deposit_amount" v-model="order.additional_deposit_amount" :value="order.additional_deposit_amount" v-bind="money" class="form-control"></money>
										<error-message :errors="errors" field="additional_deposit_amount"></error-message>
									</ValidationProvider>
								</div>
										</template>
									</PaymentMethod>
								</div>
							</div>

							<div :class="order?.created_without_collect_rental_fees || (order && order.data_version == null) ? 'd-none' : ''"><hr/></div>

							<div class="row" v-if="!is_deposit_contract_mode && !order.deposit_closed" :class="order?.created_without_collect_rental_fees || (order && order.data_version == null) ? 'd-none' : ''">
								<div class="form-group col-md-12">
									<h5 class="mr-5"><strong>Thu phí thuê xe</strong></h5>
									<div class="checkbox-wrapper mt-2" v-if="!id || id == null">
										<input type="checkbox" class="checkbox-input" v-model="order.create_order_without_input_rental_fee" id="create_order_without_input_rental_fee"/>
										<label for="create_order_without_input_rental_fee" class="mb-0"><strong>Tạo hợp đồng mà không thu phí thuê xe</strong></label>
									</div>
								</div>
							</div>

							<div class="row" v-if="!is_deposit_contract_mode && !order.create_order_without_input_rental_fee && !order.deposit_closed" :class="order?.created_without_collect_rental_fees || (order && order.data_version == null) ? 'd-none' : ''">
								<div class="col-12">
									<PaymentMethod label="Hình thức thu phí thuê" :settings="order.total_rental_payment_method" :banks="banks" :fixedAmount="totalFeeAllOrderItems" @setting_changed="order_total_rental_fee_changed" class="contract-payment-method">
										<template #amount>
											<div class="form-group col-md-3">
									<label for="paid"><strong>Tổng phí thuê xe</strong></label>
									<ValidationProvider name="Tổng phí thuê xe" rules="min_value:0" mode="lazy" v-slot="{ errors }" vid="amount">
										<money id="paid" :value="totalFeeAllOrderItems" v-bind="money" class="form-control" disabled></money>
										<error-message :errors="errors" field="amount"></error-message>
									</ValidationProvider>
								</div>
										</template>
									</PaymentMethod>
								</div>
							</div>
						</div>

						<div class="col-md-3 right-column" v-if="!is_deposit_contract_mode && !order.create_order_without_input_deposit && !order.create_order_without_input_rental_fee && !order.deposit_closed" style="border-left: 1px solid #DCDFE6;">
							<div class="form-group">
								<div v-if="start_this_contract">
									<label for="paid"><strong>Tổng tiền cần thu thêm</strong></label>
									<ValidationProvider name="Tổng tiền cần thu thêm" mode="lazy" v-slot="{ errors }" vid="amount">
										<money disabled="" id="paid" :value="totalAdditionalCharge" v-bind="money" class="form-control"></money>
										<error-message :errors="errors" field="amount"></error-message>
									</ValidationProvider>
								</div>
								<div v-else>
									<label for="paid"><strong>Tổng thu</strong></label>
									<ValidationProvider name="Tổng" mode="lazy" v-slot="{ errors }" vid="amount">
										<money disabled="" id="paid" :value="total_in" v-bind="money" class="form-control">
										</money>
										<error-message :errors="errors" field="amount"></error-message>
									</ValidationProvider>
								</div>
							</div>

							<div class="form-group" v-if="order && !order.created_without_collect_deposit && !order.created_without_collect_rental_fees">
								<label for="totalFeeAllOrderItems"><strong>Tổng phí (Phí thuê xe + Phí khác)</strong></label>
								<money id="totalFeeAllOrderItems" :value="totalFeeAllOrderItems" v-bind="money" class="form-control" :disabled="true"></money>
							</div>

							<div class="form-group" v-if="order && totalRaiseAndAddonVal > 0">
								<label for="totalRaiseAndAddonVal"><strong>Tổng gia hạn</strong></label>
								<money id="totalRaiseAndAddonVal" :value="totalRaiseAndAddonVal" v-bind="money" class="form-control" :disabled="true"></money>
							</div>

							<div class="form-group" v-if="order && order.order_status == 'completed' && !order.created_without_collect_deposit && !order.created_without_collect_rental_fees && order.default_refund_amount && order.default_refund_amount != debt">
								<label for="debt"><strong>Cần hoàn trả khách(mặc định)</strong>
									<el-tooltip content="Số tiền này do app tính toán dựa vào tiền cọc và chi phí thuê.">
										<span class="font-size-xs text-muted ml-1">[?]</span>
									</el-tooltip>
								</label>
								<money id="debt" :value="order.default_refund_amount ? order.default_refund_amount : debt" v-bind="money" class="form-control" :disabled="true"></money>
							</div>
							<div class="form-group" v-if="order && order.order_status == 'completed' && typeof order.custom_refund_amount === 'number' && !order.created_without_collect_deposit && !order.created_without_collect_rental_fees">
								<label for="debt"><strong>Đã hoàn trả khách(tùy chỉnh)</strong>
									<el-tooltip content="Số tiền này do nhân viên tùy chỉnh: giá trị do nhân viên nhập vao khác với giá trị do app tính toán.">
										<span class="font-size-xs text-muted ml-1">[?]</span>
									</el-tooltip>
								</label>
								<money id="debt" :value="order.custom_refund_amount" v-bind="money" class="form-control" :disabled="true"></money>
							</div>
							<div class="form-group" v-else>
								<div v-if="!is_deposit_contract_mode && order && !order.created_without_collect_deposit && !order.created_without_collect_rental_fees">
									<label for="debt" v-if="debt >= 0"><strong>Tạm tính khách còn nợ</strong></label>
									<label for="debt" v-else-if="order && order.order_status == 'completed'"><strong>Đã hoàn trả khách</strong></label>
									<label for="debt" v-else><strong>Cần hoàn trả khách</strong></label>
									<money id="debt" :value="debt" v-bind="money" class="form-control" :disabled="true"></money>
								</div>
							</div>
						</div>
					</div>
					
                    <!-- Thông tin ký kết & Tài sản thế chấp theo hợp đồng Himoto -->
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label for="contract_collateral"><strong>Tài sản thế chấp / Đặt cọc tài sản</strong></label>
                            <el-input
                                class="w-100"
                                placeholder="VD: 01 Đăng ký xe mô tô BKS 29X1-..., 01 CCCD gốc, v.v."
                                id="contract_collateral"
                                type="textarea"
                                :rows="2"
                                v-model="order.contract_collateral_description">
                            </el-input>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label><strong>Người đại diện ký Bên A (Himoto)</strong></label>
                            <el-input
                                placeholder="Tên nhân viên đại diện Bên A"
                                v-model="order.contract_signer_a_name">
                            </el-input>
                        </div>
                        <div class="col-md-6 form-group">
                            <label><strong>Người ký Bên B (Khách thuê)</strong></label>
                            <el-input
                                placeholder="Họ tên người thuê ký hợp đồng"
                                v-model="order.contract_signer_b_name">
                            </el-input>
                        </div>
                    </div>

                    <!-- Ảnh biên bản bàn giao xe / hiện trạng -->
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label><strong>Ảnh biên bản bàn giao & hiện trạng xe</strong></label>
                            <div class="border rounded p-3 text-center bg-light text-muted" style="border-style: dashed !important; border-width: 2px;">
                                <div class="font-weight-bold mb-1">Ảnh biên bản bàn giao (sắp hỗ trợ)</div>
                                <div class="font-size-sm">Khu vực tải và lưu trữ ảnh biên bản bàn giao xe, chữ ký hiện trường đang được kết nối hạ tầng.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label for="note"><strong>Ghi chú</strong></label>
                            <el-input class="w-100" placeholder="Ghi chú hợp đồng" id="note" type="textarea"
                                v-model="order.note"></el-input>
                        </div>
                    </div>

                    <div class="row" v-if="id">
                        <div class="col-md-12 form-group">
                            <label for="warning"><strong>Cảnh báo</strong></label>
                            <el-input class="w-100" placeholder="Cảnh báo khách hàng" id="warning" type="textarea"
                                v-model="order.warning"></el-input>
                        </div>
                    </div>

                    <el-collapse v-if="id" accordion>
                        <el-collapse-item name="1">
                            <template slot="title">
                                Xem lịch sử đơn & giao dịch
                            </template>
                            <el-tabs type="card">
                                <el-tab-pane label="Lịch sử thanh toán">
                                    <transaction-history :transaction-logs="transactionLogs" :order_status="order.order_status" @addOnSuccess="addOnSuccess" @transaction_updated="transactionHistoryChanged"></transaction-history>
                                </el-tab-pane>
                                <el-tab-pane label="Lịch sử đơn hàng">
                                    <activity-history :order_status="order.order_status"
                                        :activity-logs="activityLogs"></activity-history>
                                </el-tab-pane>
                            </el-tabs>
                        </el-collapse-item>
                    </el-collapse>

					<div class="my-2 bad-debt-checkbox checkbox-wrapper">
						<input type="checkbox" class="checkbox-input" v-model="badDebt"
							:disabled="order.order_status === 'completed'" id="bad-debt">
						<label for="bad-debt">Nợ xấu</label>
					</div>

					<div class="update-order-buttons card-toolbar mt-3 d-flex justify-content-center"
						v-if="parent !== 'vehicle-revenue'">
						<ModalComplete v-if="id && order.order_status == 'renting' && !editing_item_fee" :order="order" :banks="banks" :bank_outs="bank_outs" :debt="debt"
							@addOnSuccess="addOnSuccess" @calc_before_order_complete="calc_before_order_complete">
						</ModalComplete>

						<button type="button" class="btn btn-sm btn-outline-primary mr-2 font-weight-bold" :disabled="previewLoading" @click="onPreviewContract">
							<span v-if="previewLoading">Đang chuẩn bị...</span>
							<span v-else>Xem trước hợp đồng</span>
						</button>

						<el-button v-if="!id" native-type="submit" class="btn btn-sm btn-success mr-2"
							style="color: #fff" :loading="loading">
							Lưu hợp đồng
						</el-button>
						<el-button v-if="
							id &&
							(order.order_status !== HOAN_THANH ||
								currentUser.role_id === 1)
						" native-type="submit" class="btn btn-sm btn-success mr-2" style="color: #fff"
							:loading="loading" :disabled="Boolean(order && (order.contract_is_locked || (order.contract_snapshot && order.contract_snapshot.is_locked)))">
							<span v-if="start_this_contract">Kích hoạt hợp đồng</span>
							<span v-else-if="order && (order.contract_is_locked || (order.contract_snapshot && order.contract_snapshot.is_locked))">Hợp đồng đã chốt</span>
							<span v-else>Cập nhật</span>
						</el-button>

						<button v-if="id" type="button" class="btn btn-sm btn-info mr-2 font-weight-bold" @click="onPrintOfficialContract">
							In hợp đồng
						</button>
						<ModalAddOnPrice v-if="id && order.order_status == 'renting'" :order="order" :banks="banks" @addOnSuccess="addOnSuccess"></ModalAddOnPrice>

						<ModalCloseDeposit v-if="id && order && order.order_status == 'deposit_contract'" :order_id="id" @onSuccess="onCloseDepositOrderSuccess"></ModalCloseDeposit>

					</div>
                </div>
            </form>
        </ValidationObserver>

		<ModalContractPreview v-model="showPreviewModal" :doc="previewDocumentDto" />
    </div>
</template>

<script>
import { Money } from "v-money";
import { CREATE_ORDER_CAR_RENTAL } from "../../../../core/services/store/order.module";
import ErrorMessage from "../../common/ErrorMessage";
import ItemsOrder from "./ItemsOrder";
// import ExtendHistory from "./ExtendHistory";
import { BANK_GET_ALL, BANK_INDEX } from "@/core/services/store/banks.module";
import { STORE_GET_ALL } from "@/core/services/store/store.module";
import {
    PRICE_VEHICLES_INDEX,
    VEHICLE_GET_ALL,
} from "@/core/services/store/vehicle.module";
import moment from "moment";
import { mapGetters } from "vuex";
import {
    SHOW_ORDER_CAR_RENTAL,
    UPDATE_ORDER_CAR_RENTAL,
    LOCK_ORDER_CONTRACT,
    PREVIEW_ORDER_CONTRACT,
    GET_ORDER_DOCUMENT,
} from "../../../../core/services/store/order.module";
import { HOAN_THANH } from "../../../../option/orderOption";
import ActivityHistory from "./ActivityHistory";
import ModalAddOnPrice from "./ModalAddOnPrice";
import ModalComplete from "./ModalComplete";
import ModalCloseDeposit from "./ModalCloseDeposit";
import ModalStart from "./ModalStart";
import TransactionHistory from "./TransactionHistory";
import ModalContractPreview from "./ModalContractPreview";
import { CUSTOMER_INDEX } from "@/core/services/store/customers.module";
import { LEAD_INDEX } from "@/core/services/store/lead.module";
import Swal from "sweetalert2";
import PaymentMethod from "../../components/PaymentMethod";

export default {
    name: "OrderUpdate",
    components: {
        ItemsOrder,
        ActivityHistory,
        ModalAddOnPrice,
        ModalComplete,
        ModalCloseDeposit,
        ModalStart,
        TransactionHistory,
        PaymentMethod,
        ModalContractPreview,
        ErrorMessage,
        Money,
    },
    props: {
        parent: {
            type: String,
            default: () => {
                return "";
            },
        },
        id: {
            type: Number,
            default: () => {
                return 0;
            },
        },
    },

    data() {
        return {
            HOAN_THANH: HOAN_THANH,
            loadingLock: false,
            showPreviewModal: false,
            previewDocumentDto: null,
            previewLoading: false,
            banks: [],
			bank_outs: [], // Bank dùng để trả tiền thừa cho khách.
            hiringFeeAllItems: 0,
			contract_type: 1,
            otherFeeAllOrderItems: 0,
            totalFeeAllOrderItems: 0,
            customerSearchSeq: 0,

            order: {
				created_at: new Date(),
                contract_number: "",
                contract_signed_on: new Date(),
                is_authorized_contract: false,
                contract_authorization_date: null,
                contract_authorization_party_name: "",
                contract_collateral_description: "",
                contract_signer_a_name: "",
                contract_signer_b_name: "",
                transaction_ids_to_destroy: [],
                store_id: "",
                customer_name: "",
                customer_phone: "",
                customer_id_card: "",
                customer_id_card_issued_on: null,
                customer_id_card_issued_by: "",
                customer_address: "",
                relatives: [
                    { name: "", relationship: "", phone: "" },
                    { name: "", relationship: "", phone: "" },
                ],
                warning: "",
                note: "",

                transactions: [],
                order_items: [],

                first_deposit_amount: 0,
				first_deposit_payment_method: {
					payment_method: 1,
					bank_id: null,
					bank_transfer_amount: 0,
					cash_amount: 0,
				},

				total_rental_fees: 0,
				total_rental_payment_method: {
					bank_id: null,
					bank_transfer_amount: 0, 
					cash_amount: 0,
					payment_method: 1,
				},

                payment_method: 1,
                bank_id: null,

                other_fee_payment_method: 1,
                other_fee_bank_id: null,

				additional_deposit_amount: 0,
				additional_deposit_payment_method: {
					payment_method: 1,
					bank_id: null,
					bank_transfer_amount: 0,
					cash_amount: 0,
				},

				create_order_without_input_deposit: false,
				create_order_without_input_rental_fee: false,

				data_version: 2,
            },

            stores: [],
            vehicles: [],
            priceVehicles: [],

            loading: false,
            loadingComplete: false,
            loadingComponent: false,
            /* v-money */
            money: {
                decimal: ",",
                thousands: ".",
                prefix: "",
                suffix: " VNĐ",
                precision: 0,
                masked: false,
            },
            activityLogs: [],
            transactionLogs: [],
            extendLogs: [],

            warningTemp: "",
            leadIds: [],
            leads: [],
			using_payment_method: 1,
			cash_amount: 0,
			bank_transfer_amount: 0,
			start_this_contract: false,
			
			total_deposit: 0,
			editing_item_fee: false,

			editing_order_created_at: false,
        };
    },
    components: {
        // AddOnHistory,
        ModalComplete,
        ModalAddOnPrice,
        ErrorMessage,
        ItemsOrder,
        Money,
        // ExtendHistory,
        ActivityHistory,
        TransactionHistory,
		ModalStart,
		PaymentMethod,
		ModalCloseDeposit,
    },

    computed: {
        ...mapGetters(["currentUser"]),
		is_deposit_contract_mode() {
			if (this.start_this_contract) {
				return false; // Nếu người dùng chọn start_this_contract=true thì sẽ cần show các normal fields.
			}
			if ( this.order && this.order.order_status == 'deposit_contract' ) {
				return true;
			}
			let calc = 2 == this.contract_type;
			return calc;
		},

        debt() {
			// if ( this.order.custom_refund_amount && this.order.custom_refund_amount != 0) {
			// 	return -1 * this.order.custom_refund_amount;
			// }

			this.total_outdate_early_amount = this.order.order_items.reduce( // Tính thêm khoản tiền quá hạn, khoản tiền hoàn do trả sớm.
                (accu, item) => {
                    return accu + item.money_out_date;
                }, 0 
			);

            let debt = this.totalFeeAllOrderItems + this.total_outdate_early_amount - this.total_in;
			if ( this.order && this.order.data_version == null ) {
				debt = this.total_outdate_early_amount - (this.total_in - this.totalFeeAllOrderItems);
			}

			return debt;
        },
        totalRaiseAndAddonVal() {
            return this.transactionLogs.reduce((accu, tran) => {
                if (
                    tran.name.includes("raise") ||
                    tran.name.includes("addon")
                ) {
                    return accu + tran.value;
                } else {
                    return accu;
                }
            }, 0);
        },
        total_in() {
			let otherDebt = this.transactionLogs.reduce( (sum, item) => {
				let amount = 0;
				if ( item.type == 'in' && item.name.includes("order:complete") && item.note.includes('thu nợ order') && item.value ) {
					amount = item.value;
				}
				return sum = sum + amount;
			}, 0);

            let total = this.order.first_deposit_amount + this.totalRaiseAndAddonVal + this.order.additional_deposit_amount + otherDebt;
			/*
			if ( this.order.total_rental_fees ) {
				console.log('Total in - this.order.total_rental_fees: ', this.order.total_rental_fees);
				total = total + this.order.total_rental_fees;
			}
			console.log('Total in - totalFeeAllOrderItems: ', this.totalFeeAllOrderItems);
			if (!this.id && this.totalFeeAllOrderItems) {
				console.log('Plus - totalFeeAllOrderItems: ', this.totalFeeAllOrderItems);
				total = total + this.totalFeeAllOrderItems;
			}
			*/

			if (this.totalFeeAllOrderItems > 0) {
				if (this.order.created_without_collect_rental_fees) {
					// This order didn't collect the rental fees.
				} else if (this.order && this.order.data_version == null) {

				} else {
					total = total + this.totalFeeAllOrderItems; // The total must be plus the totalFeeAllOrderItems
				}
			}
			return total;
        },

		totalAdditionalCharge() {
			return this.order.additional_deposit_amount + this.totalFeeAllOrderItems;
		},

		calcTotalDeposit() {
			let depositTotal = this.transactionLogs.reduce( (sum, item) => {
				let amount = 0;
				if ( item.name.includes("deposit") && item.value ) {
					console.log('item: ', item);
					amount = item.value;
				}
				return sum = sum + amount;
			}, 0);
			
			
			return depositTotal;
		},

        firstDepositValInput: {
            get() {
				if (this.order.first_deposit_amount) { // Temp disable to check.
					return this.order.first_deposit_amount;
				}
                const deposit_tran = this.firstDepositTransaction;
                if (!deposit_tran) {
                    return 0;
                }
                return (deposit_tran || []).reduce((acc, item) => {
					acc += item.value;
					return acc;
				}, 0);
            },
            set(value) {
                const deposit_tran = this.firstDepositTransaction;
                if (deposit_tran?.length > 0) {
					if (this.order) {
						this.order.first_deposit_amount = value;
					}
                    // deposit_tran.value = value;
                } else {
                    this.order.first_deposit_amount = value;
                }
				if (this.order && this.order.data_version == null) {
					this.order.v1_total_rental_fees = this.totalFeeAllOrderItems;
				}
            },
        },

        firstDepositTransaction() {
            // let tran = this.transactionLogs.find((item) =>
            //     item.name.includes("deposit"),
            // );

			let tran = this.transactionLogs.filter((item) =>
                item.name.includes("deposit"),
            );

			console.log('The first deposit: ', tran);
			
            if (tran) {
                return tran.reverse();
            }
            return null;
        },

        firstDepositBankIdInput: {
            get() {
                const deposit_tran = this.firstDepositTransaction;
                if (!deposit_tran || deposit_tran.length == 0) {
                    return this.order.bank_id;
                }
                return deposit_tran[0].bank_id;
            },
            set(value) {
                const deposit_tran = this.firstDepositTransaction;
                if (!deposit_tran || deposit_tran.length == 0) {
                    this.order.bank_id = value;
                } else {
                    deposit_tran[0].bank_id = value;
                }
            },
        },

        firstDepositPayMethodInput: {
            get() {
                const deposit_tran = this.firstDepositTransaction;
                if (!deposit_tran || deposit_tran.length == 0) {
                    return this.order.payment_method;
                }
                return deposit_tran[0].payment_method;
            },
            set(value) {
                const deposit_tran = this.firstDepositTransaction;
                if (!deposit_tran || deposit_tran.length == 0) {
                    this.order.payment_method = value ? 1 : 2;
                } else {
                    deposit_tran[0].payment_method = value ? 1 : 2;
                }
            },
        },
        firstDepositRules() {
            return this.order.payment_method == 1 ? "" : "required";
        },

        badDebt: {
            get() {
                return this.order.order_status == "bad_debt";
            },

            set(val) {
                this.order.order_status = val ? "bad_debt" : "renting";
            },
        },
    },

    async created() {
        if (this.id) {
            await this.getOrder();
        } else {
            this.addVehicle();
        }
        // this.getBank();
        await this.getStore();
        await this.getListVehicles();

        await this.getListVehiclesPrice();
        this.calOrderFee();

		if (this.firstDepositTransaction && this.firstDepositTransaction.length > 0) {
			this.firstDepositTransaction.forEach(tran => {
				if (tran.payment_method == 3) {
					this.using_payment_method = 3;
					if ( tran.value > 0 && tran.bank_id ) {
						this.bank_transfer_amount = tran.value;
					}
					if ( tran.value > 0 && tran.cash_id ) {
						this.cash_amount = tran.value;
					}
				} else if (tran.payment_method == 2) {
					this.using_payment_method = 2;
				}
			});
		}

    },
    watch: {
        "order.order_items": {
            handler: "watchOrderItems",
            deep: true,
        },
        firstDepositValInput(value) {
            this.order.first_deposit_amount = value;
        },
        firstDepositBankIdInput(value) {
            this.order.bank_id = value;
        },
        firstDepositPayMethodInput(value) {
            this.order.payment_method = value ? 1 : 2;
        },
		bank_transfer_amount(value) {
			if ( this.using_payment_method == 3 ) {
				let total = this.firstDepositValInput || this.order.first_deposit_amount;
				if (this.start_this_contract) {
					total = this.order.additional_deposit_amount;
				}
				let diff = total - value;
				this.cash_amount = diff;
			}
		},
		cash_amount(value) {
			if ( this.using_payment_method == 3 ) {
				let total = this.firstDepositValInput || this.order.first_deposit_amount;
				if (this.start_this_contract) {
					total = this.order.additional_deposit_amount;
				}
				let diff = total - value;
				this.bank_transfer_amount = diff;
			}
		},
		start_this_contract(value) {
			console.log('start_this_contract changed: ', value);
			if (value) {
				this.bank_transfer_amount = 0;
				this.cash_amount = 0;
			}
		},
    },
    methods: {
		async onPreviewContract() {
			if (!this.order.customer_name || !this.order.customer_id_card) {
				Swal.fire({
					title: "Thiếu thông tin khách hàng",
					text: "Vui lòng nhập họ tên và số CCCD của khách thuê trước khi xem trước hợp đồng.",
					icon: "warning",
					confirmButtonText: "Đã hiểu",
				});
				const customerEl = document.getElementById("customer_name") || document.querySelector("input[name='Tên khách hàng']");
				if (customerEl) customerEl.focus();
				return;
			}
			if (!this.order.order_items || this.order.order_items.length === 0 || !this.order.order_items[0].vehicle_id) {
				Swal.fire({
					title: "Chưa chọn xe thuê",
					text: "Vui lòng chọn ít nhất 1 xe thuê để xem trước hợp đồng.",
					icon: "warning",
					confirmButtonText: "Đã hiểu",
				});
				return;
			}

			this.previewLoading = true;
			try {
				const payload = {
					...this.order,
					customer_name: this.order.customer_name,
					customer_phone: this.order.customer_phone,
					customer_id_card: this.order.customer_id_card,
					customer_id_card_issued_on: this.order.customer_id_card_issued_on,
					customer_id_card_issued_by: this.order.customer_id_card_issued_by,
					customer_address: this.order.customer_address,
					customer_relatives: this.order.relatives,
					order_items: this.order.order_items,
					store_id: this.order.store_id,
					contract_signed_on: this.order.contract_signed_on,
					contract_signer_a_name: this.order.contract_signer_a_name,
					contract_signer_b_name: this.order.contract_signer_b_name || this.order.customer_name,
					contract_responsible_user_id: this.order.contract_responsible_user_id,
					contract_responsible_user_name: this.currentUser ? this.currentUser.name : '',
					contract_authorization_date: this.order.contract_authorization_date,
					contract_authorization_party_name: this.order.contract_authorization_party_name,
					contract_collateral_description: this.order.contract_collateral_description,
					deposit_amount: this.firstDepositValInput || this.order.first_deposit_amount,
					total_rental_fees: this.order.total_rental_fees || this.order.total,
					total_rental_payment_method: this.order.total_rental_payment_method,
					deposit_payment_method: this.order.first_deposit_payment_method || 1,
					unit_price: this.order.order_items.length === 1 ? this.order.order_items[0].contract_unit_price : null,
					paid_amount: 0,
				};
				const res = await this.$store.dispatch(PREVIEW_ORDER_CONTRACT, payload);
				this.previewDocumentDto = res.data || res;
				this.showPreviewModal = true;
			} catch (err) {
				const msg = (err && err.data && err.data.message) || "Không thể tải bản xem trước hợp đồng";
				Swal.fire("Lỗi", msg, "error");
			} finally {
				this.previewLoading = false;
			}
		},

		async onPrintOfficialContract() {
			if (!this.id) return;
			this.previewLoading = true;
			try {
				const res = await this.$store.dispatch(GET_ORDER_DOCUMENT, this.id);
				this.previewDocumentDto = res.data || res;
				this.showPreviewModal = true;
			} catch (err) {
				const msg = (err && err.data && err.data.message) || "Không thể tải tài liệu hợp đồng";
				Swal.fire("Lỗi", msg, "error");
			} finally {
				this.previewLoading = false;
			}
		},

		async onCloseDepositOrderSuccess() {
			await this.getOrder();
		},
		editingOrderCreatedAt() {
			this.editing_order_created_at = !this.editing_order_created_at;
		},
		showAccType(type) {
			if (1 == type) {
				return 'TK Thu';
			} else if (2 == type) {
				return 'TK Chi';
			}
			return 'TK Thu & Chi';
		},
        calOriginalUnitPrice(order_item, rangeDays) {
            if (!this.priceVehicles) {
                console.log("Không tìm thấy this.priceVehicles");
                return 0;
            }
            let priceVehicle;

            let vehicle = this.vehicles.find(
                (vehicle) => order_item.vehicle_id == vehicle.id,
            );

            if (!vehicle) {
                console.log("Không tìm thấy vehicle");
                return 0;
            }

            if (order_item.is_all_in_one) {
                priceVehicle = this.priceVehicles.find((price) => {
                    if (
                        price.from_date <= rangeDays &&
                        price.to_date >= rangeDays &&
                        price.type === vehicle.type &&
                        price.price_type === "total" &&
                        price.from_year <= vehicle.year &&
                        price.to_year >= vehicle.year
                    ) {
                        return true;
                    }

                });
            } else {
                priceVehicle = this.priceVehicles.find((price) => {

                    if (
                        price.from_date <= rangeDays &&
                        price.to_date >= rangeDays &&
                        price.type === vehicle.type &&
                        price.price_type === "day" &&
                        price.from_year <= vehicle.year &&
                        price.to_year >= vehicle.year
                    ) {

                        return true;
                    }

                });
            }

            if (!priceVehicle) {
                console.log(
                    "Không tìm thấy đơn giá cho phương tiện đã chọn với thời gian thuê đã chọn",
                );
                return 0; // k tìm thấy đơn giá
            }
            return priceVehicle.price
        },

        setHiringFeeEachItem() {
            for (let order_item of this.order.order_items) {
                const _MS_PER_DAY = 1000 * 60 * 60 * 24;
                let remainHours = Math.floor(
                    ((Date.parse(order_item.return_at) - Date.parse(order_item.rent_at)) / (3600 * 1000)) % 24,
                );
                let rangeDays = Math.floor(
                    (Date.parse(order_item.return_at) - Date.parse(order_item.rent_at)) /
                    _MS_PER_DAY,
                );
                var moneyRemainHours = 0;
                if (remainHours > 0 && remainHours < 8) {
					if ( this.vehicles && this.vehicles.length> 0 ) {
						let vehicle = this.vehicles.find(
							(vehicle) => order_item.vehicle_id == vehicle.id,
						);
						if (vehicle) {
							switch (vehicle.type) {
								case "xeso":
								case "xega":
									moneyRemainHours = remainHours * 15000;
									break;
								case "xecon":
									moneyRemainHours = remainHours * 25000;
									break;
								case "sh":
									moneyRemainHours = remainHours * 35000;
									break;
							}
						}
					}
                } else if (remainHours >= 8) {
                    rangeDays += 1;
                }

                let unitPrice
                if (order_item.substitute_unit_price > 0) {
                    unitPrice = order_item.substitute_unit_price
                } else {
                    unitPrice = this.calOriginalUnitPrice(order_item, rangeDays);
					order_item.default_unit_price = unitPrice; // Bảng giá thuê mặc định theo ngày của xe này.
					order_item.rental_days = rangeDays; // Tổng số ngày thuê xe
                }

				this.$set(order_item, 'contract_unit_price', order_item.is_all_in_one || order_item.custom_hiring_fee > 0 ? null : unitPrice);
				if (order_item.custom_hiring_fee && order_item.custom_hiring_fee > 0) {
					order_item.hiringFee = order_item.custom_hiring_fee;
				} else {
					order_item.hiringFee = order_item.is_all_in_one ? unitPrice : unitPrice * rangeDays + moneyRemainHours;
					// if (order_item.hiring_fee && order_item.hiring_fee != order_item.hiringFee) {
					// 	order_item.hiringFee = order_item.hiring_fee;
					// }
				}

            }
        },
        watchOrderItems(newItems, oldItems) {
			this.calOrderFee();
        },
        calOrderFee() {
            this.setHiringFeeEachItem();
            this.hiringFeeAllItems = this.order.order_items.reduce(
                (accu, item) => {
                    if (item.handler_price) {
                        return accu + parseInt(item.handler_price);
                    } else {
                        return (
                            // accu + parseInt(item.hiringFee) + parseInt(item.money_out_date) // Code by VanNguyen
                            accu + parseInt(item.hiringFee) // Don't plus money_out_date to result.
                        );
                    }
                },
                0,
            );

            this.otherFeeAllOrderItems = this.order.order_items.reduce(
                (accu, item) => accu + otherFeeOneOrderItem(item),
                0,
            );

			let totalFees = parseInt(this.hiringFeeAllItems) + parseInt(this.otherFeeAllOrderItems);
			if (this.order.custom_refund_amount != null) { // Case contract custom_refund_amount is set.
				totalFees = parseInt(this.order.total);
			}

            this.totalFeeAllOrderItems = totalFees;

            function otherFeeOneOrderItem(orderItem) {
                if (!orderItem.order_item_fees) {
                    return 0;
                }
                return orderItem.order_item_fees.reduce(
                    (accu, fee) => accu + fee.value,
                    0,
                );
            }
        },
        addOnSuccess() {
            this.$emit("updateSuccess");
        },

		async transactionHistoryChanged() {
			await this.getOrder();
		},

        async getBankByStoreId(id) {
            if (id == null) {
                return;
            }

            id = parseInt(id);
            await this.$store
                .dispatch(BANK_INDEX, { store_id: id, account_type: 10 })
                .then((data) => {
                    const banks = data?.data?.data || [];
                    const [f] = banks || [];
                    if (this.firstDepositTransaction?.length > 0 && this.firstDepositTransaction[0]?.bank_id == null) {
                        if (f && f.id != null) {
                            this.firstDepositBankIdInput = f.id;
                        }
                    }
                    this.banks = banks;
                })
                .catch(() => {
                    this.banks = [];
                });
        },
		async getBankOutByStoreId(id) {
            if (id == null) {
                return;
            }
            id = parseInt(id);
            await this.$store
				.dispatch(BANK_INDEX, { store_id: id, account_type: 20 })
				.then((data) => {
					const banks = data?.data?.data || [];
					this.bank_outs = banks;
				})
				.catch(() => {
					this.bank_outs = [];
				});
        },

        onStoreChange(val) {
            this.firstDepositBankIdInput = null;
            this.order.other_fee_bank_id = null;
            this.getBankByStoreId(val);
			this.getBankOutByStoreId(val);
        },

        deleteFee(id) {
            this.order.transaction_ids_to_destroy.push(id);
			this.editing_item_fee = true;
        },
		addFee() {
			this.editing_item_fee = true;
		},
		feeChanged() {
			this.editing_item_fee = true;
		},
        async getOrder() {
            this.loadingComponent = true;
            await this.$store
                .dispatch(SHOW_ORDER_CAR_RENTAL, this.id)
                .then((res) => {
					console.log('after order before: ', this.order.additional_deposit_amount);
                    let relatives = [
                        { name: "", relationship: "", phone: "" },
                        { name: "", relationship: "", phone: "" },
                    ];
                    if (res.data.customer?.relatives && Array.isArray(res.data.customer.relatives) && res.data.customer.relatives.length > 0) {
                        relatives = [
                            res.data.customer.relatives[0] || { name: "", relationship: "", phone: "" },
                            res.data.customer.relatives[1] || { name: "", relationship: "", phone: "" },
                        ];
                    }

                    this.order = {
                        ...this.order,
                        ...res.data,
                        contract_number: res.data.contract_number || "",
                        contract_is_locked: !!(res.data.contract_is_locked || (res.data.contract_snapshot && res.data.contract_snapshot.is_locked)),
                        contract_snapshot: res.data.contract_snapshot || null,
                        contract_signed_on: res.data.contract_signed_on || res.data.created_at || new Date(),
                        is_authorized_contract: !!(res.data.contract_authorization_date || res.data.contract_authorization_party_name),
                        contract_authorization_date: res.data.contract_authorization_date || null,
                        contract_authorization_party_name: res.data.contract_authorization_party_name || "",
                        contract_collateral_description: res.data.contract_collateral_description || "",
                        contract_signer_a_name: res.data.contract_signer_a_name || "",
                        contract_signer_b_name: res.data.contract_signer_b_name || "",
                        customer_name: res.data.customer?.name || "",
                        warning: res.data.customer?.warning || "",
                        customer_phone: res.data.customer?.phone || "",
                        customer_id_card: res.data.customer?.id_card || "",
                        customer_id_card_issued_on: res.data.customer?.id_card_issued_on || null,
                        customer_id_card_issued_by: res.data.customer?.id_card_issued_by || "",
                        customer_address: res.data.customer?.address || "",
                        relatives: relatives,
                        order_items: res.data.order_items.map((item) => ({
                            ...item,
                            driver_name: item.driver_name || "",
                            driver_license_number: item.driver_license_number || "",
                            driver_license_issued_on: item.driver_license_issued_on || null,
                            borrow_raincoats: item.borrow_raincoats || 0,
                            hiringFee: item.total_money,
                            is_all_in_one: item.type == 'total' ? true : false,
							custom_hiring_fee: item.hiring_fee, // Using this key to set custom rental_fee for exists order.
                        })),
                    };

                    this.order.other_fee_bank_id =
                        res.data.order_items[0]?.order_item_fees[0]?.bank_id ||
                        null;
                    this.order.other_fee_payment_method =
                        res.data.order_items[0]?.order_item_fees[0]
                            ?.payment_method || 1;
                    this.transactionLogs = res.data.transactions;

                    this.activityLogs = res.data.activity_logs;
                    this.getBankByStoreId(this.order.store_id);
                    this.getBankOutByStoreId(this.order.store_id);
                })
                .finally(() => (this.loadingComponent = false));
        },
        handleLockContract() {
            Swal.fire({
                title: "Chốt thông tin hợp đồng?",
                text: "Sau khi chốt, thông tin hợp đồng đã ký sẽ được cố định và không thể sửa đổi.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Đồng ý chốt",
                cancelButtonText: "Hủy thao tác"
            }).then((result) => {
                if (result.isConfirmed) {
                    this.loadingLock = true;
                    this.$store
                        .dispatch(LOCK_ORDER_CONTRACT, this.id)
                        .then(() => {
                            Swal.fire("Thành công", "Chốt hợp đồng thành công", "success");
                            this.getOrder();
                            this.$emit("updateSuccess");
                        })
                        .catch((err) => {
                            const msg = (err && err.data && err.data.message) || "Chốt hợp đồng thất bại";
                            Swal.fire("Lỗi", msg, "error");
                        })
                        .finally(() => {
                            this.loadingLock = false;
                        });
                }
            });
        },
        async getStore() {
            await this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data.data;
            });
        },

        async getListVehicles() {
            let params = {

                is_all: true,
                status: "ready",
            };
            await this.$store.dispatch(VEHICLE_GET_ALL, params).then((data) => {
                const merged = [
                    ...(this.order.vehicles || []),
                    ...(data?.data || []),
                ];

                let idMap = new Map();
                let uniqueArray = merged.filter((item) => {
                    if (!idMap.has(item.id)) {
                        idMap.set(item.id, true);
                        return true;
                    }
                    return false;
                });

                this.vehicles = uniqueArray;
            });
        },
        async getListVehiclesPrice() {
            await this.$store
                .dispatch(PRICE_VEHICLES_INDEX, {})
                .then((data) => {
                    this.priceVehicles = data.data;
                });
        },

        addVehicle() {
            const nowDate = new Date();
            this.order.order_items.push({
                vehicle_id: "",
				custom_hiring_fee: null,
                hiringFee: 0,
                price_id: 0,
                borrow_hats: 0,
                borrow_raincoats: 0,
                driver_name: "",
                driver_license_number: "",
                driver_license_issued_on: null,
                rent_at: nowDate,
                return_at: nowDate,
                money_out_date: 0,
                minute_out_date: 0,
                completed_at: null,
                total_money: 0,
                status: 1,
                type: "",
                deleted_at: null,
                created_at: null,
                updated_at: null,
                substitute_unit_price: 0,
                order_item_fees: [],
            });
        },
        deleteVehicle(index) {
            this.order.order_items.splice(index, 1);
        },
        changeOtherFeeBankId(val) {
            this.order.other_fee_bank_id = val;
        },
        changeOtherFeePaymentMethod(val) {
            this.order.other_fee_payment_method = val;
        },
        changeRentAt({ index, rent_at }) {
            this.$set(this.order.order_items, index, {
                ...this.order.order_items[index],
                rent_at,
            });
        },
        changeIsAllInOne({ index, data }) {
            this.$set(this.order.order_items, index, {
                ...this.order.order_items[index],
                is_all_in_one: data,
            });
        },
        changeBorrowHats({ index, data }) {
            this.$set(this.order.order_items, index, {
                ...this.order.order_items[index],
                borrow_hats: data,
            });
        },
		item_hiring_fee_changed({ index, data }) {
			this.$set(this.order.order_items, index, {
                ...this.order.order_items[index],
				custom_hiring_fee: data,
            });
		},
		odometerBeforeChanged({ index, data }) {
			this.$set(this.order.order_items, index, {
                ...this.order.order_items[index],
                odometer_before: data,
            });
		},
		odometerAfterChanged({ index, data }) {
			this.$set(this.order.order_items, index, {
                ...this.order.order_items[index],
                odometer_after: data,
            });
		},
        changeReturnAt({ index, data }) {
            this.$set(this.order.order_items, index, {
                ...this.order.order_items[index],
                return_at: data,
            });
        },
        changeVehicleId({ index, data }) {
            this.$set(this.order.order_items, index, {
                ...this.order.order_items[index],
                vehicle_id: data,
            });
        },
        change_substitute_unit_price({ index, data }) {
            this.$set(
                this.order.order_items, index,
                {
                    ...this.order.order_items[index],
                    substitute_unit_price: data,
                });
            this.calOrderFee();
        },
        changeHandlerPrice({ index, data }) {
            this.$set(this.order.order_items, index, {
                ...this.order.order_items[index],
                handler_price: data,
            });
            this.calOrderFee();
        },
		change_money_out_date({ index, data }) {
            this.$set(
                this.order.order_items, index,
                {
                    ...this.order.order_items[index],
                    money_out_date: data,
                });
            this.calOrderFee();
        },
        resetForm() {

            this.order = {
                store_id: "",
                customer_name: "",
                customer_phone: "",
                customer_id_card: "",
                customer_id_card_issued_on: null,
                customer_id_card_issued_by: "",
                customer_address: "",
                relatives: [
                    { name: "", relationship: "", phone: "" },
                    { name: "", relationship: "", phone: "" },
                ],
                contract_number: "",
                contract_signed_on: new Date(),
                is_authorized_contract: false,
                contract_authorization_date: null,
                contract_authorization_party_name: "",
                contract_collateral_description: "",
                contract_signer_a_name: "",
                contract_signer_b_name: "",
                note_item: "",
                note: "",
				order_items: [],
                total: 0,
                debt: 0,
            };
            this.totalPrices = [{ total_price: 0 }];
        },


        prepareRequestParams() {
            this.$set(this.order, "total", this.totalFeeAllOrderItems);
            this.$set(this.order, "pid", this.total_in);
            this.$set(this.order, "debt", this.debt);
			this.$set(this.order, "payment_method", this.using_payment_method );
			this.$set(this.order, "total_rental_fees", this.totalFeeAllOrderItems); // Tổng phí thuê của tất cả các line items.

			if (this.start_this_contract) {
				this.$set(this.order, "start_this_contract", this.start_this_contract );
			}

            const formattedItems = this.order.order_items.map((item) => ({
                ...item,

                type: item.is_all_in_one ? 'total' : 'day',
                total_money: item.hiringFee,
                rent_at: moment(item.rent_at).format('DD-MM-YYYY HH:mm:ss'),
                return_at: moment(item.return_at).format('DD-MM-YYYY HH:mm:ss'),
				custom_total_money: item.custom_hiring_fee,
                driver_name: item.driver_name || "",
                driver_license_number: item.driver_license_number || "",
                driver_license_issued_on: item.driver_license_issued_on ? moment(item.driver_license_issued_on).format('YYYY-MM-DD') : null,
                borrow_raincoats: item.borrow_raincoats || 0,
            }));
            this.order.order_items = formattedItems;

            const cleanRelatives = (this.order.relatives || [])
                .filter(r => r && (r.name || r.relationship || r.phone))
                .map(r => ({
                    name: (r.name || "").trim(),
                    relationship: (r.relationship || "").trim(),
                    phone: (r.phone || "").trim(),
                }));

            const idCardIssuedOn = this.order.customer_id_card_issued_on ? moment(this.order.customer_id_card_issued_on).format('YYYY-MM-DD') : null;
            const idCardIssuedBy = (this.order.customer_id_card_issued_by || "").trim();

            return {
                ...this.order,
                contract_signed_on: this.order.contract_signed_on ? moment(this.order.contract_signed_on).format('YYYY-MM-DD') : null,
                contract_authorization_date: (this.order.is_authorized_contract && this.order.contract_authorization_date) ? moment(this.order.contract_authorization_date).format('YYYY-MM-DD') : null,
                contract_authorization_party_name: this.order.is_authorized_contract ? (this.order.contract_authorization_party_name || "") : "",
                customer_id_card_issued_on: idCardIssuedOn,
                customer_id_card_issued_by: idCardIssuedBy,
                id_card_issued_on: idCardIssuedOn,
                id_card_issued_by: idCardIssuedBy,
                relatives: cleanRelatives,
                // get lead_ids
                leads: this.leadIds && this.leadIds.length > 0 ? this.leadIds : undefined,
				contract_type: this.contract_type,
            };
        },
        handleFormSubmit() {
            if (this.id) {
                this.onSubmit();
            } else {
                this.onSubmitCreate();
            }
        },

        onSubmit() {
			Swal.fire({
                title: this.start_this_contract ? "Bạn chắc chắn muốn kích hoạt hợp đồng này?" : "Bạn chắc chắn muốn sửa lại hợp đồng này?",
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: "Đồng ý",
                cancelButtonText: "Hủy thao tác",
            }).then((result) => {
                if (result.isConfirmed) {
                    this.loading = true;
					let params = this.prepareRequestParams();
					if ( this.editing_order_created_at ) {
						params['editing_order_created_at'] = true;
						params['created_at'] = moment(params['created_at']).format('DD-MM-YYYY HH:mm:ss');
					}
					
					let payload = {
						id: this.id,
						params,
					};
					this.$store
						.dispatch(UPDATE_ORDER_CAR_RENTAL, payload)
						.then((res) => {
							this.$emit("updateSuccess");
							this.noticeMessage(
								"success",
								"Cập nhật hợp đồng thành công",
								res.data?.message,
							);
						})
						.catch((err) => {
							this.noticeMessage("error", this.start_this_contract ? "Không kích hoạt được hợp đồng" : "Không cập nhật được hợp đồng", err.data?.message);
						})
						.finally(() => (this.loading = false));
                }
            });
        },

        onSubmitCreate() {
            this.loading = true;
            let params = this.prepareRequestParams();
			if (params.created_at) {
				params.created_at = moment(this.order.created_at).format('DD-MM-YYYY HH:mm:ss');
			}

            this.$store
                .dispatch(CREATE_ORDER_CAR_RENTAL, params)
                .then((res) => {
                    this.$emit("createSuccess");
                    this.resetForm();
                    this.noticeMessage(
                        "success",
                        "Tạo hợp đồng thành công",
                        res.data?.message,
                    );
                })
                .catch((err) => {
                    this.noticeMessage("error", "Không tạo được hợp đồng", err.data?.message);
                })
                .finally(() => (this.loading = false));
        },

        onBlurCardId(event, errors) {
            const hasError = (errors || []).length > 0;
            if (!hasError) {
                const val = event?.target?.value;
                const name = event?.target?.name;
                if (val) {

                    this.getCustomerByCardId(val, name);
                    this.getLeads();
                } else {
                    this.resetCustomerInfo();
                }
            }
        },
        onChangeCardId(value) {
            if (!value) {
                this.resetCustomerInfo();
            }
        },
        resetCustomerInfo() {
            this.customerSearchSeq++;
            this.order.customer_address = "";
            this.order.customer_name = "";
            this.order.customer_phone = "";
            this.order.customer_id_card = "";
            this.order.customer_id_card_issued_on = null;
            this.order.customer_id_card_issued_by = "";
            this.order.relatives = [
                { name: "", relationship: "", phone: "" },
                { name: "", relationship: "", phone: "" },
            ];
            this.warningTemp = "";
        },
        getCustomerByCardId(val, name) {
            if (!val) {
                this.resetCustomerInfo();
                return;
            }
            const querySeq = ++this.customerSearchSeq;
            const params = {
                [name]: val,
            };
            this.$store.dispatch(CUSTOMER_INDEX, params).then((data) => {
                if (this.customerSearchSeq !== querySeq) {
                    return;
                }
                if (name === 'id_card' && this.order.customer_id_card !== val) {
                    return;
                }
                if (name === 'phone' && this.order.customer_phone !== val) {
                    return;
                }
                const arr = data?.data?.data || [];
                // Luôn reset danh sách người thân để tránh lẫn thông tin giữa các khách hàng
                this.order.relatives = [
                    { name: "", relationship: "", phone: "" },
                    { name: "", relationship: "", phone: "" },
                ];
                if (Array.isArray(arr) && arr.length > 0) {
                    const { address, name, phone, warning, id_card, id_card_issued_on, id_card_issued_by, relatives } =
                        arr[0] || {};
                    this.order.customer_address = address || "";
                    this.order.customer_name = name || "";
                    this.order.customer_id_card = id_card || "";
                    this.order.customer_id_card_issued_on = id_card_issued_on || null;
                    this.order.customer_id_card_issued_by = id_card_issued_by || "";
                    if (relatives && Array.isArray(relatives) && relatives.length > 0) {
                        this.order.relatives = [
                            relatives[0] || { name: "", relationship: "", phone: "" },
                            relatives[1] || { name: "", relationship: "", phone: "" },
                        ];
                    }
                    this.order.customer_phone = phone || "";
                    this.warningTemp = warning || "";
                } else {
                    this.warningTemp = "";
                }
            }).catch(() => {
                if (this.customerSearchSeq !== querySeq) {
                    return;
                }
                this.warningTemp = "";
            });
        },

        getLeads() {
            console.log("getLeads!")
            if (!(this.order.customer_phone)) {
                this.leadIds = [];
                this.leads = [];
                return;
            }
            const params = {
                leads_for_order: true,
                customer_phone: this.order.customer_phone || "",

            };

            this.$store
                .dispatch(LEAD_INDEX, params)
                .then((res) => {
                    const data = (res && res.data && res.data.data) || [];
                    this.leads = data;
                    const leadIdsValid = (data || [])
                        .map((item) =>
                            item && item.status === "pending" ? item.id : null,
                        )
                        .filter(Boolean);

                    this.leadIds = leadIdsValid;
                })
                .catch(() => {
                    this.leadIds = [];
                });
        },
		async calc_before_order_complete() {
			await this.getOrder();
		},
		order_first_deposit_changed(val) {
			this.order.first_deposit_payment_method = val;
		},
		order_total_rental_fee_changed(val) {
			this.order.total_rental_payment_method = val;
		},
		additional_deposit_amount_changed(val) {
			this.order.additional_deposit_payment_method = val;
		},
        changeDriverName({ index, data }) {
            this.$set(this.order.order_items, index, {
                ...this.order.order_items[index],
                driver_name: data,
            });
        },
        changeDriverLicenseNumber({ index, data }) {
            this.$set(this.order.order_items, index, {
                ...this.order.order_items[index],
                driver_license_number: data,
            });
        },
        changeDriverLicenseIssuedOn({ index, data }) {
            this.$set(this.order.order_items, index, {
                ...this.order.order_items[index],
                driver_license_issued_on: data,
            });
        },
        changeBorrowRaincoats({ index, data }) {
            this.$set(this.order.order_items, index, {
                ...this.order.order_items[index],
                borrow_raincoats: data,
            });
        },
    },
};
</script>

<style>
.delete-vehicle {
    position: absolute;
    left: 178px;
    top: 2px !important;
    font-size: 10px;
    cursor: pointer;
    z-index: 10;
}

.list-vehicles {
    position: relative;
}

.fa-minus-circle:hover {
    color: red;
}

.bad-debt-checkbox.checkbox-wrapper,
.deposit-contract-checkbox.checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}

.bad-debt-checkbox .checkbox-input,
.deposit-contract-checkbox .checkbox-input {
    appearance: none;
    width: 24px;
    height: 24px;
    border: 2px solid #999;
    border-radius: 5px;
    outline: none;
    cursor: pointer;
    position: relative;
}

.bad-debt-checkbox .checkbox-input:checked{
    background-color: #a50707;
    border: 2px solid red;
}

.deposit-contract-checkbox .checkbox-input:checked {
    background-color: #009688;
    border: 2px solid #009688;
}

.bad-debt-checkbox .checkbox-input:checked::after,
.deposit-contract-checkbox .checkbox-input:checked::after {
    content: "✔";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 16px;
    color: white;
}

.bad-debt-checkbox .checkbox-name,
.deposit-contract-checkbox .checkbox-name {
    font-size: 16px;
}

.bad-debt-checkbox label,
.deposit-contract-checkbox label {
    font-weight: bold;
    font-size: 15px;
    margin-top: 5px;
}

.bad-debt-checkbox label {
	color: red;
}

.deposit-contract-checkbox input[type="checkbox"]:disabled+label,
.deposit-contract-checkbox input[type="checkbox"]:disabled,
.bad-debt-checkbox input[type="checkbox"]:disabled+label,
.bad-debt-checkbox input[type="checkbox"]:disabled {
    color: #aaa;
    cursor: not-allowed;
}
</style>
