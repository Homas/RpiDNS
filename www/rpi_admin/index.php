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
    <b-container fluid  class="h-100 d-flex flex-column">
        <b-tabs ref="i2r" tabs pills vertical nav-wrapper-class="menu-bkgr h-100" class="h-100 corners" content-class="curl_angels" :value="cfgTab" @input="changeTab" v-bind:nav-class="{ hidden: (toggleMenu == 2 && windowInnerWidth>=992) || (toggleMenu == 1 && windowInnerWidth<992) }" >
					<i v-cloak class="fa fa-angle-double-left border rounded-right border-dark" style="position: absolute;left: -2px;top: 10px;z-index: 1; cursor: pointer;" v-bind:class="{ hidden: (toggleMenu == 2 && windowInnerWidth>=992) || (toggleMenu == 1 && windowInnerWidth<992) }" v-on:click="toggleMenu += 1;this.update_window_size(this)"></i>
					<i v-cloak class="fa fa-angle-double-right border rounded-right border-dark" style="position: absolute;left: -2px;top: 10px;z-index: 1; cursor: pointer;" v-bind:class="{ hidden: (toggleMenu != 2 && windowInnerWidth>=992) || (toggleMenu != 1 && windowInnerWidth<992) }" v-on:click="toggleMenu = 0;this.update_window_size(this)"></i>
          <b-tab class="scroll_tab">
						<template slot="title"><i class="fa fa-igloo"></i><span class="d-none d-lg-inline" v-bind:class="{ hidden: toggleMenu>0 }">&nbsp;&nbsp;Dashboard</span></template>
						<!--Home page href="#/cfg/home"-->

						<!--End Home page-->
          </b-tab>

          <b-tab>
					<template slot="title"><i class="fas fa-shoe-prints"></i><span class="d-none d-lg-inline" v-bind:class="{ hidden: toggleMenu>0 }">&nbsp;&nbsp;Query log</span></template>
						<!--Query log href="#/cfg/home"-->

							<div>
							
								<b-card >
									<template slot="header"><span class="bold"><i class="fas fa-shoe-prints"></i>&nbsp;&nbsp;Query logs</span></template>
									<b-row class='d-none d-sm-flex'>
										<b-col cols="1" lg="1"  class="m-auto">
											<b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl('qlogs')"><i class="fa fa-sync"></i></b-button>
										</b-col>
										<b-col cols="1" lg="4">
										</b-col>
										<b-col cols="10" lg="7" class="m-auto">
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
													<b-table id="qlogs" ref="refLogs" :sticky-header="`${logs_height}px`" no-border-collapse striped hover :items="get_tables" api-url="/rpi_admin/rpidata.php?req=queries_raw" :fields="qlogs_fields" small responsive :no-provider-paging=true :no-provider-sorting=true :no-provider-filtering=true :filter="qlogs_Filter">
														<template v-slot:cell(action)="row">
															<div v-if="row.item.action == 'blocked'"><i class="fas fa-hand-paper"></i> Block</div>
															<div v-else><i class="fas fa-check"></i> Allow</div>
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

          <b-tab>
					<template slot="title"><i class="fa fa-shield-alt"></i><span class="d-none d-lg-inline" v-bind:class="{ hidden: toggleMenu>0 }">&nbsp;&nbspRPZ hits</span></template>
						<!--RPZ hits page href="#/cfg/home"-->

							<div>
							
								<b-card >
									<template slot="header"><span class="bold"><i class="fas fa-shoe-prints"></i>&nbsp;&nbsp;RPZ hits</span></template>
									<b-row class='d-none d-sm-flex'>
										<b-col cols="1" lg="1"  class="m-auto">
											<b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl('hits')"><i class="fa fa-sync"></i></b-button>
										</b-col>
										<b-col cols="1" lg="4">
										</b-col>
										<b-col cols="10" lg="7" class="m-auto">
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
													<b-table id="hits" ref="refHits" :sticky-header="`${logs_height}px`" no-border-collapse striped hover :items="get_tables" api-url="/rpi_admin/rpidata.php?req=hits_raw" :fields="hits_fields" small responsive :no-provider-paging=true :no-provider-sorting=true :no-provider-filtering=true :filter="hits_Filter">
              							
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
