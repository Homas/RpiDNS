<?php
#(c) Vadim Pavlov 2020
#rpidns 
	require_once "/opt/rpidns/www/rpidns_vars.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title>rpidns</title>
	<link type="text/css" rel="stylesheet" href="//unpkg.com/bootstrap/dist/css/bootstrap.min.css"/>
	<link type="text/css" rel="stylesheet" href="//unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.css"/>    
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.1/css/all.css" crossorigin="anonymous">
	<link type="text/css" rel="stylesheet" href="/rpi_admin/css/rpi_admin.css?<?=$rpiver?>"/>
</head>
<body>
	<div id="app" fluid class="h-100 d-flex flex-column" v-cloak>
		<div id="ConfApp" class="h-100 d-flex flex-column" v-cloak>
			<div class="menu-bkgr white pl-4 pt-2"><span style="font-size: 32px">RpiDNS</span> powered by <a href="https://ioc2rpz.net" target="_blank">ioc2rpz.net</a></div>
			<b-container fluid  class="h-100 d-flex flex-column">
        <b-tabs ref="i2r" tabs pills vertical nav-wrapper-class="menu-bkgr h-100 p-1" class="h-100 corners" content-class="curl_angels" :value="cfgTab" @input="changeTab" v-bind:nav-class="{ hidden: (toggleMenu == 2 && windowInnerWidth>=992) || (toggleMenu == 1 && windowInnerWidth<992) }" >
					<i v-cloak class="fa fa-angle-double-left border rounded-right border-dark" style="position: absolute;left: -2px;top: 10px;z-index: 1; cursor: pointer;" v-bind:class="{ hidden: (toggleMenu == 2 && windowInnerWidth>=992) || (toggleMenu == 1 && windowInnerWidth<992) }" v-on:click="toggleMenu += 1;this.update_window_size(this);this.window.localStorage.setItem('toggleMenu',toggleMenu);"></i>
					<i v-cloak class="fa fa-angle-double-right border rounded-right border-dark" style="position: absolute;left: -2px;top: 10px;z-index: 1; cursor: pointer;" v-bind:class="{ hidden: (toggleMenu != 2 && windowInnerWidth>=992) || (toggleMenu != 1 && windowInnerWidth<992) }" v-on:click="toggleMenu = 0;this.update_window_size(this);this.window.localStorage.setItem('toggleMenu',toggleMenu);"></i>
          <b-tab class="scroll_tab">
						<template slot="title"><i class="fa fa-tachometer-alt"></i><span class="d-none d-lg-inline" v-bind:class="{ hidden: toggleMenu>0 }">&nbsp;&nbsp;Dashboard</span></template>
						<!--Dashboard page href="#/cfg/home"-->
							<div fluid v-cloak>
								<div class="v-spacer"></div>							
								<b-card no-body class="d-flex flex-column" style="max-height:calc(100vh - 100px)">
									<template slot="header">
										<b-row>
											<b-col cols="2" lg="2"><span class="bold"><i class="fas fa-tachometer-alt"></i>&nbsp;&nbsp;Dashboard</span></b-col>
											<b-col cols="10" lg="10" class="text-right">
												<b-form-group class="m-0">
													<b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshDash"><i class="fa fa-sync"></i></b-button>&nbsp;&nbsp;&nbsp;
													<b-form-radio-group v-model="dash_period" :options="period_options" buttons size="sm" @input="refreshDashQPS"></b-form-radio-group>
												</b-form-group>
											</b-col>
										</b-row>
									</template>
									<div class="v-spacer"></div>									
									<div>
										<b-card-group deck>
											<b-card header="TopX Allowed Requests" body-class="p-2">
												<div>
													<b-table id="dash_topX_req" sticky-header="150px" no-border-collapse striped hover small :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=dash_topX_req&period='+this.dash_period" :fields="dash_stats_fields" thead-class="hidden" @row-clicked="(item, index, event) =>  {this.qlogs_Filter=item.name;this.qlogs_period=this.dash_period;this.cfgTab=1;}"></b-table>
												</div>
											</b-card>
											<b-card header="TopX Allowed Request Types" body-class="p-2">
												<div>
													<b-table id="dash_topX_req_type" sticky-header="150px" no-border-collapse striped hover small :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=dash_topX_req_type&period='+this.dash_period" :fields="dash_stats_fields" thead-class="hidden" @row-clicked="(item, index, event) =>  {this.qlogs_Filter=item.name;this.qlogs_period=this.dash_period;this.cfgTab=1;}"></b-table>
												</div>
											</b-card>
											<b-card header="TopX Allowed Clients" body-class="p-2">
												<div>
													<b-table id="dash_topX_client" sticky-header="150px" no-border-collapse striped hover small :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=dash_topX_client&period='+this.dash_period" :fields="dash_stats_fields" thead-class="hidden" @row-clicked="(item, index, event) =>  {this.qlogs_Filter=item.name;this.qlogs_period=this.dash_period;this.cfgTab=1;}"></b-table>
												</div>
											</b-card>
										</b-card-group>
									</div>
									<div class="v-spacer"></div>							
									<div>
										<b-card-group deck>
											<b-card header="TopX Blocked Requests" body-class="p-2">
												<div>
													<b-table id="dash_topX_breq" sticky-header="150px" no-border-collapse striped hover small :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=dash_topX_breq&period='+this.dash_period" :fields="dash_stats_fields" thead-class="hidden" @row-clicked="(item, index, event) =>  {this.hits_Filter=item.name;this.hits_period=this.dash_period;this.cfgTab=2;}"></b-table>
												</div>
											</b-card>
											<b-card header="TopX Blocked Clients" body-class="p-2">
												<div>
													<b-table id="dash_topX_bclient" sticky-header="150px" no-border-collapse striped hover small :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=dash_topX_bclient&period='+this.dash_period" :fields="dash_stats_fields" thead-class="hidden" @row-clicked="(item, index, event) =>  {this.hits_Filter=item.name;this.hits_period=this.dash_period;this.cfgTab=2;}"></b-table>
												</div>
											</b-card>
											<b-card header="TopX Feeds" body-class="p-2">
												<div>
													<b-table id="dash_topX_feeds" sticky-header="150px" no-border-collapse striped hover small :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=dash_topX_feeds&period='+this.dash_period" :fields="dash_stats_fields" thead-class="hidden" @row-clicked="(item, index, event) =>  {this.hits_Filter=item.name;this.hits_period=this.dash_period;this.cfgTab=2;}"></b-table>
												</div>
											</b-card>
										</b-card-group>
									</div>
									<div class="v-spacer"></div>							
									<div>
										<b-card-group deck>
											<b-card header="Queries per Minute">
												<apexchart type="area" height="200" :options="qps_options" :series="qps_series"></apexchart>
											</b-card>
										</b-card-group>
									</div>									
								</b-card>
							</div>
						<!--End Dashboard page-->
          </b-tab>

          <b-tab  @click="refreshTbl('qlogs')">
					<template slot="title"><i class="fas fa-shoe-prints"></i><span class="d-none d-lg-inline" v-bind:class="{ hidden: toggleMenu>0 }">&nbsp;&nbsp;Query log</span></template>
						<!--Query log href="#/cfg/home"-->

							<div>
								<div class="v-spacer"></div>							
								<b-card >
									<template slot="header">
										<b-row>
											<b-col cols="2" lg="2"><span class="bold"><i class="fas fa-shoe-prints"></i>&nbsp;&nbsp;Query logs</span></b-col>
											<b-col cols="10" lg="10" class="text-right">
												<b-form-group class="m-0"><b-form-radio-group v-model="qlogs_period" :options="qperiod_options" buttons size="sm" @change="qlogs_cp=1"></b-form-radio-group></b-form-group>
											</b-col>
										</b-row>
									</template>
									<b-row class='d-none d-sm-flex'>
										<b-col cols="1" lg="1">
											<b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl('qlogs')"><i class="fa fa-sync"></i></b-button>
										</b-col>
										<b-col cols="3" lg="3">
											
										</b-col>
										<b-col cols="3" lg="3">
											<b-pagination v-model="qlogs_cp" :total-rows="qlogs_nrows" :per-page="qlogs_pp" aria-controls="qlogs" size="sm" pills align="center" first-number last-number></b-pagination>
										</b-col>
										<b-col cols="5" lg="5">
											<b-form-group label-cols-md="4" label-size="sm" >
												<b-input-group>
													<b-input-group-text slot="prepend" size="sm"><i class="fas fa-filter fa-fw" size="sm"></i></b-input-group-text>
													<b-form-input v-model="qlogs_Filter" placeholder="Type to search" size="sm"></b-form-input>
													<b-button size="sm" slot="append" :disabled="!qlogs_Filter" @click="qlogs_Filter = ''">Clear</b-button>
												</b-input-group>
											</b-form-group>
										</b-col>
									</b-row>
							
							
							
									<b-row>
										<b-col sm="12">
											<template>
												<div ref="refLogsDiv">
													<b-table id="qlogs" ref="refLogs" :sticky-header="`${logs_height}px`" :per-page="qlogs_pp" :current-page="qlogs_cp" no-border-collapse striped hover :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=queries_raw&period='+this.qlogs_period+'&cp='+this.qlogs_cp+'&filter='+this.qlogs_Filter+'&pp='+this.qlogs_pp" :fields="qlogs_fields" small responsive :filter="qlogs_Filter" sort-by="dtz" :sort-desc="true">
														<template v-slot:cell(action)="row">
															<div v-if="row.item.action == 'blocked'"><i class="fas fa-hand-paper salmon"></i> Block</div>
															<div v-else><i class="fas fa-check green"></i> Allow</div>
														</template>                							
													</b-table>
							
												</div>
											</template>
										</b-col>
									</b-row>
							
								</b-card>
							
							
							</div>

						<!--Query log page-->
          </b-tab>

          <b-tab @click="refreshTbl('hits')">
					<template slot="title"><i class="fa fa-shield-alt"></i><span class="d-none d-lg-inline" v-bind:class="{ hidden: toggleMenu>0 }">&nbsp;&nbspRPZ hits</span></template>
						<!--RPZ hits page href="#/cfg/home"-->

							<div>
								<div class="v-spacer"></div>							
								<b-card >
									<template slot="header">
									<b-row>
										<b-col cols="2" lg="2"><span class="bold"><i class="fas fa-shoe-prints"></i>&nbsp;&nbsp;RPZ hits</span></b-col>
										<b-col cols="10" lg="10" class="text-right">
											<b-form-group class="m-0"><b-form-radio-group v-model="hits_period" :options="period_options" buttons size="sm" @change="hits_cp=1"></b-form-radio-group></b-form-group>
										</b-col>
									</b-row>
									</template>
									<b-row class='d-none d-sm-flex'>
										<b-col cols="1" lg="1" >
											<b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl('hits')"><i class="fa fa-sync"></i></b-button>
										</b-col>
										<b-col cols="3" lg="3">
											
										</b-col>
										<b-col cols="3" lg="3">
											<b-pagination v-model="hits_cp" :total-rows="hits_nrows" :per-page="hits_pp" aria-controls="hits" size="sm" pills align="center" first-number last-number></b-pagination>
										</b-col>
										<b-col cols="5" lg="5">
											<b-form-group label-cols-md="4" label-size="sm" >
												<b-input-group>
													<b-input-group-text slot="prepend" size="sm"><i class="fas fa-filter fa-fw" size="sm"></i></b-input-group-text>
													<b-form-input v-model="hits_Filter" placeholder="Type to search" size="sm"></b-form-input>
													<b-button size="sm" slot="append" :disabled="!hits_Filter" @click="hits_Filter = ''">Clear</b-button>
												</b-input-group>
											</b-form-group>
										</b-col>
									</b-row>
							
							
							
									<b-row>
										<b-col sm="12">
											<template>
												<div ref="refLogsDiv">
													<b-table id="hits" ref="refHits" :sticky-header="`${logs_height}px`" :per-page="hits_pp" :current-page="hits_cp" no-border-collapse striped hover :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=hits_raw&period='+this.hits_period+'&cp='+this.hits_cp+'&filter='+this.hits_Filter+'&pp='+this.hits_pp" :fields="hits_fields" small responsive :filter="hits_Filter" sort-by="dtz" :sort-desc="true">
              							<!-- <template v-slot:cell(client_ip)="data"><span v-html="data.value"></span></template> -->
													</b-table>
							
												</div>
											</template>
										</b-col>
									</b-row>
							
								</b-card>
							
							
							</div>

						<!--End RPZ hits page-->
          </b-tab>

          <b-tab>
						<template slot="title"><i class="fas fa-hands-helping"></i><span class="d-none d-lg-inline" v-bind:class="{ hidden: toggleMenu>0 }">&nbsp;&nbsp;Help</span></template>
						<!--Help page href='#/cfg/help'-->
	
						<!--End Help page-->
          </b-tab>

      </b-tabs>
    </b-container>


<!--        -->
<!-- Modals -->
<!--        -->		
		</div>
	
	</div>
	
	<div class="copyright"><p>Copyright © 2020 Vadim Pavlov</p></div>
		<script src="https://cdn.jsdelivr.net/npm/vue@latest/dist/vue.js"></script>
		<script src="//unpkg.com/babel-polyfill@latest/dist/polyfill.min.js"></script>
		<script src="//unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.js"></script>
		<script src="//unpkg.com/axios/dist/axios.min.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
		<script src="https://cdn.jsdelivr.net/npm/vue-apexcharts"></script>
		<script src="/rpi_admin/js/rpi_admin.js?<?=$rpiver?>"></script>		
	</body>
</html>
