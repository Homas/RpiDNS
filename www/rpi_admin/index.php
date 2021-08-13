<?php
#(c) Vadim Pavlov 2020
#rpidns
	require_once "/opt/rpidns/www/rpidns_vars.php";
	require_once "/opt/rpidns/www/rpisettings.php";
	$AddressType=$assets_by=="mac"?"MAC":"IP";
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title>rpidns</title>

<!--
	<link type="text/css" rel="stylesheet" href="//unpkg.com/bootstrap/dist/css/bootstrap.min.css"/>
	<link type="text/css" rel="stylesheet" href="//unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.css"/>
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.1/css/all.css" crossorigin="anonymous">
-->
	<link type="text/css" rel="stylesheet" href="/rpi_admin/css/bootstrap.min.css"/>
	<link type="text/css" rel="stylesheet" href="/rpi_admin/css/bootstrap-vue.min.css"/>
	<link rel="stylesheet" href="/rpi_admin/css/all.css" />

	<link type="text/css" rel="stylesheet" href="/rpi_admin/css/rpi_admin.css?<?=$rpiver?>"/>
</head>
<body>
	<div id="app" fluid class="h-100 d-flex flex-column" v-cloak>
		<div id="ConfApp" class="h-100 d-flex flex-column" v-cloak>
			<div class="menu-bkgr white pl-4 pt-2"><span style="font-size: 32px">RpiDNS</span> powered by <a href="https://ioc2rpz.net" target="_blank">ioc2rpz.net</a></div>
			<b-container fluid  :class="{'h-100':true, 'd-flex':true, 'flex-column':true, 'p-0':windowInnerWidth<=500}">
        <b-tabs ref="i2r" tabs pills :vertical="windowInnerWidth>500" lazy :nav-wrapper-class="{'menu-bkgr': true, 'h-100':windowInnerWidth>500 ,'p-1':windowInnerWidth>500}" class="h-100 corners" content-class="curl_angels" v-model="cfgTab" @input="changeTab" v-bind:nav-class="{ hidden: (toggleMenu == 2 && windowInnerWidth>=992) || (toggleMenu == 1 && windowInnerWidth<992) }" >
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
											<b-col cols="0" class="d-none d-lg-block" lg="2"><span class="bold"><i class="fas fa-tachometer-alt"></i>&nbsp;&nbsp;Dashboard</span></b-col>
											<b-col cols="12" lg="10" class="text-right">
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
													<b-table id="dash_topX_req" sticky-header="150px" no-border-collapse striped hover small :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=dash_topX_req&period='+this.dash_period" :fields="dash_stats_fields" thead-class="hidden" @row-clicked="(item, index, event) =>  {this.qlogs_Filter='fqdn='+item.fname;this.qlogs_period=this.dash_period;this.cfgTab=1;}">
														<template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
														<template v-slot:cell(fname)="row">
															<b-popover title="Actions" :target="'tip-good_requests'+row.item.fname" triggers="hover">
																<a href="javascript:{}" @click.stop="qlogs_Filter='fqdn='+row.item.fname;qlogs_period=dash_period;cfgTab=1;">Show queries</a><br>
																<a href="javascript:{}" @click.stop="addIOC=row.item.fname;addIOCtype='bl';addIOCcomment='';addBLRowID=0;addIOCactive=true;addIOCsubd=true;$emit('bv::show::modal', 'mAddIOC')">Block</a>
																<hr class="m-1">
																<strong>Research:</strong><br>
																- <a target=_blank :href="'https://duckduckgo.com/?q=%22'+row.item.fname+'%22'">DuckDuckGo</a><br>
																- <a target="_blank" :href="'https://www.google.com/search?q=%22'+row.item.fname+'%22'">Google</a><br>
																- <a target="_blank" :href="'https://www.virustotal.com/gui/search/'+row.item.fname">VirusTotal</a><br>
																- <a target="_blank" :href="'https://community.riskiq.com/search/'+row.item.fname">RiskIQ Community</a><br>
																- <a target="_blank" :href="'http://whois.domaintools.com/'+row.item.fname">DomainTools Whois</a><br>
																- <a target="_blank" :href="'https://www.robtex.com/dns-lookup/'+row.item.fname">Robtex</a><br>
                                - <a target="_blank" :href="'https://apility.io/search/'+row.item.fname">Apility.io</a><br>
                                - <a target="_blank" :href="'https://www.threatminer.org/domain.php?q='+row.item.fname">ThreatMiner</a>
															</b-popover>
															<span :id="'tip-good_requests'+row.item.fname">{{row.item.fname}}</span>
														</template>
													</b-table>
												</div>
											</b-card>

											<b-card header="TopX Allowed Clients" body-class="p-2">
												<div>
													<b-table id="dash_topX_client" sticky-header="150px" no-border-collapse striped hover small :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=dash_topX_client&period='+this.dash_period" :fields="dash_stats_fields" thead-class="hidden" @row-clicked="(item, index, event) =>  {this.qlogs_Filter=(item.mac==null || item.mac=='')?'client_ip='+item.fname:'mac='+item.mac;this.qlogs_period=this.dash_period;this.cfgTab=1;}">
														<template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
														<template v-slot:cell(fname)="row">
															<b-popover title="Actions" :target="'tip-good_clients'+row.item.fname" triggers="hover">
																<a href="javascript:{}" @click.stop="qlogs_Filter=(row.item.mac==null || row.item.mac=='')?'client_ip='+row.item.fname:'mac='+row.item.mac;qlogs_period=dash_period;cfgTab=1;">Show queries</a><br>
																<a href="javascript:{}" @click.stop="hits_Filter=(row.item.mac==null || row.item.mac=='')?'client_ip='+row.item.fname:'mac='+row.item.mac;hits_period=dash_period;cfgTab=2;">Show hits</a>
															</b-popover>
															<span :id="'tip-good_clients'+row.item.fname">{{row.item.fname}}</span>
														</template>
													</b-table>
												</div>
											</b-card>


											<b-card header="TopX Allowed Request Types" body-class="p-2">
												<div>
													<b-table id="dash_topX_req_type" sticky-header="150px" no-border-collapse striped hover small :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=dash_topX_req_type&period='+this.dash_period" :fields="dash_stats_fields" thead-class="hidden" @row-clicked="(item, index, event) =>  {this.qlogs_Filter='type='+item.fname;this.qlogs_period=this.dash_period;this.cfgTab=1;}">
														<template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
													</b-table>
												</div>
											</b-card>

											<b-card header="RpiDNS" body-class="p-2">
												<div>
													<b-table id="dash_topX_req_type" sticky-header="150px" no-border-collapse striped hover small :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=server_stats'" :fields="dash_stats_fields" thead-class="hidden">
														<template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>

														<template v-slot:cell(cnt)="row">
															<div v-if="row.item.fname == 'CPU load'">
																<b-popover :target="'tip-RpiDNS'+row.item.fname" triggers="hover" placement="topright">
																	Load in 1 minute, 5 minutes, 15 minutes
																</b-popover>
																<span :id="'tip-RpiDNS'+row.item.fname">{{row.item.cnt}}</span>
															</div>
															<div v-else>{{row.item.cnt}}</div>

														</template>

													</b-table>
												</div>
											</b-card>
										</b-card-group>

									</div>
									<div class="v-spacer"></div>
									<div>
										<b-card-group deck>
											<b-card header="TopX Blocked Requests" body-class="p-2">
												<div>
													<b-table id="dash_topX_breq" sticky-header="150px" no-border-collapse striped hover small :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=dash_topX_breq&period='+this.dash_period" :fields="dash_stats_fields" thead-class="hidden" @row-clicked="(item, index, event) =>  {this.hits_Filter='fqdn='+item.fname;this.hits_period=this.dash_period;this.cfgTab=2;}">
														<template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
														<template v-slot:cell(fname)="row">
															<b-popover title="Actions" :target="'tip-bad_requests'+row.item.fname" triggers="hover">
																Show <a href="javascript:{}" @click.stop="qlogs_Filter='fqdn='+row.item.fname;qlogs_period=dash_period;cfgTab=1;">queries</a>&nbsp;|&nbsp;
																<a href="javascript:{}" @click.stop="hits_Filter='fqdn='+row.item.fname;hits_period=dash_period;cfgTab=2;">hits</a><br>
																<a href="javascript:{}" @click.stop="addIOC=row.item.fname;addIOCtype='wl';addIOCcomment='';addBLRowID=0;addIOCactive=true;addIOCsubd=true;$emit('bv::show::modal', 'mAddIOC')">Allow</a>
																<hr class="m-1">
																<strong>Research:</strong><br>
																- <a target=_blank :href="'https://duckduckgo.com/?q=%22'+row.item.fname+'%22'">DuckDuckGo</a><br>
																- <a target="_blank" :href="'https://www.google.com/search?q=%22'+row.item.fname+'%22'">Google</a><br>
																- <a target="_blank" :href="'https://www.virustotal.com/gui/search/'+row.item.fname">VirusTotal</a><br>
																- <a target="_blank" :href="'https://community.riskiq.com/search/'+row.item.fname">RiskIQ Community</a><br>
																- <a target="_blank" :href="'http://whois.domaintools.com/'+row.item.fname">DomainTools Whois</a><br>
																- <a target="_blank" :href="'https://www.robtex.com/dns-lookup/'+row.item.fname">Robtex</a><br>
                                - <a target="_blank" :href="'https://apility.io/search/'+row.item.fname">Apility.io</a><br>
                                - <a target="_blank" :href="'https://www.threatminer.org/domain.php?q='+row.item.fname">ThreatMiner</a>
															</b-popover>
															<span :id="'tip-bad_requests'+row.item.fname">{{row.item.fname}}</span>
														</template>
													</b-table>
												</div>
											</b-card>
											<b-card header="TopX Blocked Clients" body-class="p-2">
												<div>
													<b-table id="dash_topX_bclient" sticky-header="150px" no-border-collapse striped hover small :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=dash_topX_bclient&period='+this.dash_period" :fields="dash_stats_fields" thead-class="hidden" @row-clicked="(item, index, event) =>  {this.hits_Filter=(item.mac==null || item.mac=='')?'client_ip='+item.fname:'mac='+item.mac;this.hits_period=this.dash_period;this.cfgTab=2;}">
														<template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
														<template v-slot:cell(fname)="row">
															<b-popover title="Actions" :target="'tip-bad_clients'+row.item.fname" triggers="hover">
																Show <a href="javascript:{}" @click.stop="qlogs_Filter=(row.item.mac==null || row.item.mac=='')?'client_ip='+row.item.fname:'mac='+row.item.mac;qlogs_period=dash_period;cfgTab=1;">queries</a>&nbsp;|&nbsp;
																<a href="javascript:{}" @click.stop="hits_Filter=(row.item.mac==null || row.item.mac=='')?'client_ip='+row.item.fname:'mac='+row.item.mac;hits_period=dash_period;cfgTab=2;">hits</a>
															</b-popover>
															<span :id="'tip-bad_clients'+row.item.fname">{{row.item.fname}}</span>
														</template>
													</b-table>
												</div>
											</b-card>
											<b-card header="TopX Feeds" body-class="p-2">
												<div>
													<b-table id="dash_topX_feeds" sticky-header="150px" no-border-collapse striped hover small :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=dash_topX_feeds&period='+this.dash_period" :fields="dash_stats_fields" thead-class="hidden" @row-clicked="(item, index, event) =>  {this.hits_Filter='feed='+item.fname;this.hits_period=this.dash_period;this.cfgTab=2;}">
														<template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
													</b-table>
												</div>
											</b-card>

											<b-card header="TopX Servers" body-class="p-2">
												<div>
													<b-table id="dash_topX_server" sticky-header="150px" no-border-collapse striped hover small :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=dash_topX_server&period='+this.dash_period" :fields="dash_stats_fields" thead-class="hidden" @row-clicked="(item, index, event) =>  {this.qlogs_Filter='type='+item.fname;this.qlogs_period=this.dash_period;this.cfgTab=1;}">
														<template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
													</b-table>
												</div>
											</b-card>

<!--
											<b-card header="" body-class="p-2" border-variant="light" :v-show="false">
												<div>

												</div>
											</b-card>
-->
										</b-card-group>
									</div>
									<div class="v-spacer"></div>
									<div>
										<b-card-group deck>
											<b-card header="Queries per Minute">
												<apexchart type="area" height="200" width="99%" :options="qps_options" :series="qps_series"></apexchart>
											</b-card>
										</b-card-group>
									</div>
								</b-card>
							</div>
						<!--End Dashboard page-->
          </b-tab>

          <b-tab  @click="refreshTbl('qlogs')" lazy> <!--qlogs_cp=1;-->
					<template slot="title"><i class="fas fa-shoe-prints"></i><span class="d-none d-lg-inline" v-bind:class="{ hidden: toggleMenu>0 }">&nbsp;&nbsp;Query log</span></template>
						<!--Query log href="#/cfg/home"-->

							<div>
								<div class="v-spacer"></div>
								<b-card >
									<template slot="header">
										<b-row>
											<b-col cols="0" class="d-none d-lg-block"  lg="2"><span class="bold"><i class="fas fa-shoe-prints"></i>&nbsp;&nbsp;Query logs</span></b-col>
											<b-col cols="12" lg="10" class="text-right">
												<b-form-group class="m-0">
													<b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl('qlogs')"><i class="fa fa-sync"></i></b-button>
													<b-form-radio-group v-model="qlogs_period" :options="qperiod_options" buttons size="sm" @change="qlogs_cp=1"></b-form-radio-group>
												</b-form-group>
											</b-col>
										</b-row>
									</template>
									<b-row class='d-none d-sm-flex'>
										<b-col cols="1" lg="1">
											<b-form-radio-group buttons size="sm" v-model="query_ltype" @change="switch_stats('query')">
												<b-form-radio  value="logs">Logs</b-form-radio>
												<b-form-radio  value="stats">Stats</b-form-radio>
											</b-form-radio-group>
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
													<b-form-input v-model="qlogs_Filter" placeholder="Type to search" size="sm" debounce="300"></b-form-input>
													<b-button size="sm" slot="append" :disabled="!qlogs_Filter" @click="qlogs_Filter = ''">Clear</b-button>
												</b-input-group>
											</b-form-group>
										</b-col>
									</b-row>



									<b-row>
										<b-col sm="12">
											<template>
												<div ref="refLogsDiv">
													<b-table id="qlogs" ref="refLogs" :sticky-header="`${logs_height}px`" :sort-icon-left='true' :per-page="qlogs_pp" :current-page="qlogs_cp" no-border-collapse striped hover :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=queries_raw&period='+this.qlogs_period+'&cp='+this.qlogs_cp+'&filter='+this.qlogs_Filter+'&pp='+this.qlogs_pp+'&ltype='+this.query_ltype+'&fields='+this.qlogs_select_fields" :fields="qlogs_fields" small responsive :filter="qlogs_Filter" sort-by="dtz" :sort-desc="true">
														<template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>

														<template v-slot:head(cname)="row">
															<div v-if="query_ltype == 'stats'"><b-form-checkbox name="qlog_head" :value="row.column" v-model="qlogs_select_fields">{{row.label}}</b-form-checkbox></div>
															<div v-else>{{row.label}}</div>
														</template>

														<template v-slot:head(server)="row">
															<div v-if="query_ltype == 'stats'"><b-form-checkbox name="qlog_head" :value="row.column" v-model="qlogs_select_fields">{{row.label}}</b-form-checkbox></div>
															<div v-else>{{row.label}}</div>
														</template>

														<template v-slot:head(fqdn)="row">
															<div v-if="query_ltype == 'stats'"><b-form-checkbox name="qlog_head" :value="row.column" v-model="qlogs_select_fields">{{row.label}}</b-form-checkbox></div>
															<div v-else>{{row.label}}</div>
														</template>

														<template v-slot:head(type)="row">
															<div v-if="query_ltype == 'stats'"><b-form-checkbox name="qlog_head" :value="row.column" v-model="qlogs_select_fields">{{row.label}}</b-form-checkbox></div>
															<div v-else>{{row.label}}</div>
														</template>

														<template v-slot:head(class)="row">
															<div v-if="query_ltype == 'stats'"><b-form-checkbox name="qlog_head" :value="row.column" v-model="qlogs_select_fields">{{row.label}}</b-form-checkbox></div>
															<div v-else>{{row.label}}</div>
														</template>

														<template v-slot:head(options)="row">
															<div v-if="query_ltype == 'stats'"><b-form-checkbox name="qlog_head" :value="row.column" v-model="qlogs_select_fields">{{row.label}}</b-form-checkbox></div>
															<div v-else>{{row.label}}</div>
														</template>

														<template v-slot:head(action)="row">
															<div v-if="query_ltype == 'stats'"><b-form-checkbox name="qlog_head" :value="row.column" v-model="qlogs_select_fields">{{row.label}}</b-form-checkbox></div>
															<div v-else>{{row.label}}</div>
														</template>

														<template v-slot:cell(cname)="row">
															<b-popover title="Info" :target="'tip-qlogs_cname'+row.item.rowid" triggers="hover">
																<strong>Mac:</strong> {{row.item.mac}}<br>
																<strong>IP:</strong> {{row.item.client_ip}}<br>
																<strong>Vendor:</strong> {{row.item.vendor}}<br>
																<span v-if="row.item.comment != ''"><strong>Comment:</strong> {{row.item.comment}}</span>
															</b-popover>
															<span :id="'tip-qlogs_cname'+row.item.rowid">{{row.item.cname}}</span>
														</template>
														<template v-slot:cell(action)="row">
															<b-popover title="Actions" :target="'tip-hits-action'+row.item.rowid" triggers="hover">
																<a href="javascript:{}" @click.stop="qlogs_Filter='action='+row.item.action">Filter by</a>
															</b-popover>
															<span :id="'tip-hits-action'+row.item.rowid">
																<div v-if="row.item.action == 'blocked'"><i class="fas fa-hand-paper salmon"></i> Block</div>
																<div v-if="row.item.action == 'allowed'"><i class="fas fa-check green"></i> Allow</div>
															</span>
														</template>
														<template v-slot:cell(fqdn)="row">
															<b-popover title="Actions" :target="'tip-hits'+row.item.rowid" triggers="hover">
																<div v-if="row.item.action == 'blocked'"><a href="javascript:{}" @click.stop="addIOC=row.item.fqdn;addIOCtype='wl';addIOCcomment='';addBLRowID=0;addIOCactive=true;addIOCsubd=true;$emit('bv::show::modal', 'mAddIOC')">Allow</a></div>
																<div v-else><a href="javascript:{}" @click.stop="addIOC=row.item.fqdn;addIOCtype='bl';addIOCcomment='';addBLRowID=0;addIOCactive=true;addIOCsubd=true;$emit('bv::show::modal', 'mAddIOC')">Block</a></div>
																<a href="javascript:{}" @click.stop="qlogs_Filter='fqdn='+row.item.fqdn">Filter by</a>
																<hr class="m-1">
																<strong>Research:</strong><br>
																- <a target=_blank :href="'https://duckduckgo.com/?q=%22'+row.item.fqdn+'%22'">DuckDuckGo</a><br>
																- <a target="_blank" :href="'https://www.google.com/search?q=%22'+row.item.fqdn+'%22'">Google</a><br>
																- <a target="_blank" :href="'https://www.virustotal.com/gui/search/'+row.item.fqdn">VirusTotal</a><br>
																- <a target="_blank" :href="'https://community.riskiq.com/search/'+row.item.fqdn">RiskIQ Community</a><br>
																- <a target="_blank" :href="'http://whois.domaintools.com/'+row.item.fqdn">DomainTools Whois</a><br>
																- <a target="_blank" :href="'https://www.robtex.com/dns-lookup/'+row.item.fqdn">Robtex</a><br>
                                - <a target="_blank" :href="'https://apility.io/search/'+row.item.fqdn">Apility.io</a><br>
                                - <a target="_blank" :href="'https://www.threatminer.org/domain.php?q='+row.item.fqdn">ThreatMiner</a>
															</b-popover>
															<span :id="'tip-hits'+row.item.rowid">{{row.item.fqdn}}</span>
														</template>
														<template v-slot:cell(server)="row">
															<b-popover title="Actions" :target="'tip-hits-server'+row.item.rowid" triggers="hover">
																<a href="javascript:{}" @click.stop="qlogs_Filter='server='+row.item.server">Filter by</a>
															</b-popover>
															<span :id="'tip-hits-server'+row.item.rowid">{{row.item.server}}</span>
														</template>
														<template v-slot:cell(class)="row">
															<b-popover title="Actions" :target="'tip-hits-class'+row.item.rowid" triggers="hover">
																<a href="javascript:{}" @click.stop="qlogs_Filter='class='+row.item.class">Filter by</a>
															</b-popover>
															<span :id="'tip-hits-class'+row.item.rowid">{{row.item.class}}</span>
														</template>

														<template v-slot:cell(type)="row">
															<b-popover title="Actions" :target="'tip-hits-type'+row.item.rowid" triggers="hover">
																<a href="javascript:{}" @click.stop="qlogs_Filter='type='+row.item.type">Filter by</a>
															</b-popover>
															<span :id="'tip-hits-type'+row.item.rowid">{{row.item.type}}</span>
														</template>

														<template v-slot:cell(options)="row">
															<b-popover title="Actions" :target="'tip-hits-options'+row.item.rowid" triggers="hover">
																<a href="javascript:{}" @click.stop="qlogs_Filter='options='+row.item.options">Filter by</a>
															</b-popover>
															<span :id="'tip-hits-options'+row.item.rowid">{{row.item.options}}</span>
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

          <b-tab @click="refreshTbl('hits')" lazy> <!--hits_cp=1;-->
					<template slot="title"><i class="fa fa-shield-alt"></i><span class="d-none d-lg-inline" v-bind:class="{ hidden: toggleMenu>0 }">&nbsp;&nbspRPZ hits</span></template>
						<!--RPZ hits page href="#/cfg/home"-->

							<div>
								<div class="v-spacer"></div>
								<b-card >
									<template slot="header">
									<b-row>
										<b-col cols="0" class="d-none d-lg-block"  lg="2"><span class="bold"><i class="fas fa-shoe-prints"></i>&nbsp;&nbsp;RPZ hits</span></b-col>
										<b-col cols="12" lg="10" class="text-right">
											<b-form-group class="m-0">
												<b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl('hits')"><i class="fa fa-sync"></i></b-button>
												<b-form-radio-group v-model="hits_period" :options="period_options" buttons size="sm" @change="hits_cp=1"></b-form-radio-group>
											</b-form-group>
										</b-col>
									</b-row>
									</template>
									<b-row class='d-none d-sm-flex'>
										<b-col cols="1" lg="1" >
											<b-form-radio-group buttons size="sm" v-model="hits_ltype" @change="switch_stats('hits')">
												<b-form-radio value="logs">Logs</b-form-radio>
												<b-form-radio value="stats">Stats</b-form-radio>
											</b-form-radio-group>
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
													<b-table id="hits" ref="refHits" :sticky-header="`${logs_height}px`" :sort-icon-left='true' :per-page="hits_pp" :current-page="hits_cp" no-border-collapse striped hover :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=hits_raw&period='+this.hits_period+'&cp='+this.hits_cp+'&filter='+this.hits_Filter+'&pp='+this.hits_pp+'&ltype='+this.hits_ltype+'&fields='+this.hits_select_fields" :fields="hits_fields" small responsive :filter="hits_Filter" sort-by="dtz" :sort-desc="true">
														<template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>

														<template v-slot:head(cname)="row">
															<div v-if="hits_ltype == 'stats'"><b-form-checkbox name="qlog_head" :value="row.column" v-model="hits_select_fields">{{row.label}}</b-form-checkbox></div>
															<div v-else>{{row.label}}</div>
														</template>

														<template v-slot:head(fqdn)="row">
															<div v-if="hits_ltype == 'stats'"><b-form-checkbox name="qlog_head" :value="row.column" v-model="hits_select_fields">{{row.label}}</b-form-checkbox></div>
															<div v-else>{{row.label}}</div>
														</template>

														<template v-slot:head(rule)="row">
															<div v-if="hits_ltype == 'stats'"><b-form-checkbox name="qlog_head" :value="row.column" v-model="hits_select_fields">{{row.label}}</b-form-checkbox></div>
															<div v-else>{{row.label}}</div>
														</template>

														<template v-slot:head(rule_type)="row">
															<div v-if="hits_ltype == 'stats'"><b-form-checkbox name="qlog_head" :value="row.column" v-model="hits_select_fields">{{row.label}}</b-form-checkbox></div>
															<div v-else>{{row.label}}</div>
														</template>

														<template v-slot:head(action)="row">
															<div v-if="hits_ltype == 'stats'"><b-form-checkbox name="qlog_head" :value="row.column" v-model="hits_select_fields">{{row.label}}</b-form-checkbox></div>
															<div v-else>{{row.label}}</div>
														</template>

														<template v-slot:cell(cname)="row">
															<b-popover title="Info" :target="'tip-hits_cname'+row.item.rowid" triggers="hover">
																<strong>Mac:</strong> {{row.item.mac}}<br>
																<strong>IP:</strong> {{row.item.client_ip}}<br>
																<strong>Vendor:</strong> {{row.item.vendor}}<br>
																<span v-if="row.item.comment != ''"><strong>Comment:</strong> {{row.item.comment}}</span>
															</b-popover>
															<span :id="'tip-hits_cname'+row.item.rowid">{{row.item.cname}}</span>
														</template>

														<template v-slot:cell(fqdn)="row">
															<b-popover title="Actions" :target="'tip-hits'+row.item.rowid" triggers="hover">
																<a href="javascript:{}" @click.stop="addIOC=row.item.fqdn;addIOCtype='wl';addIOCcomment='';addBLRowID=0;addIOCactive=true;addIOCsubd=true;$emit('bv::show::modal', 'mAddIOC')">Allow</a><br>
																<a href="javascript:{}" @click.stop="hits_Filter='fqdn='+row.item.fqdn">Filter by</a>
																<hr class="m-1">
																<strong>Research:</strong><br>
																- <a target=_blank :href="'https://duckduckgo.com/?q=%22'+row.item.fqdn+'%22'">DuckDuckGo</a><br>
																- <a target="_blank" :href="'https://www.google.com/search?q=%22'+row.item.fqdn+'%22'">Google</a><br>
																- <a target="_blank" :href="'https://www.virustotal.com/gui/search/'+row.item.fqdn">VirusTotal</a><br>
																- <a target="_blank" :href="'https://community.riskiq.com/search/'+row.item.fqdn">RiskIQ Community</a><br>
																- <a target="_blank" :href="'http://whois.domaintools.com/'+row.item.fqdn">DomainTools Whois</a><br>
																- <a target="_blank" :href="'https://www.robtex.com/dns-lookup/'+row.item.fqdn">Robtex</a><br>
																- <a target="_blank" :href="'https://apility.io/search/'+row.item.fqdn">Apility.io</a><br>
                                - <a target="_blank" :href="'https://www.threatminer.org/domain.php?q='+row.item.fqdn">ThreatMiner</a>
															</b-popover>
															<span :id="'tip-hits'+row.item.rowid">{{row.item.fqdn}}</span>
														</template>

														<template v-slot:cell(rule)="row">
															<template  v-if="typeof row.item.rule !== 'undefined'">
																<b-popover title="Actions" :target="'tip-hits-rule'+row.item.rowid" triggers="hover">
																	<a href="javascript:{}" @click.stop="addIOC=row.item.rule.substring( 0, row.item.rule.indexOf('.'+row.item.feed) ) ;addIOCtype='wl';addIOCcomment='';addBLRowID=0;addIOCactive=true;addIOCsubd=true;$emit('bv::show::modal', 'mAddIOC')">Allow</a><br>
																	<a href="javascript:{}" @click.stop="hits_Filter='rule='+row.item.rule">Filter by</a>
																	<hr class="m-1">
																	<strong>Research:</strong><br>
																	- <a target=_blank :href="'https://duckduckgo.com/?q=%22'+row.item.rule.substring( 0, row.item.rule.indexOf('.'+row.item.feed) )+'%22'">DuckDuckGo</a><br>
																	- <a target="_blank" :href="'https://www.google.com/search?q=%22'+row.item.rule.substring( 0, row.item.rule.indexOf('.'+row.item.feed) )+'%22'">Google</a><br>
																	- <a target="_blank" :href="'https://www.virustotal.com/gui/search/'+row.item.rule.substring( 0, row.item.rule.indexOf('.'+row.item.feed) )">VirusTotal</a><br>
																	- <a target="_blank" :href="'https://community.riskiq.com/search/'+row.item.rule.substring( 0, row.item.rule.indexOf('.'+row.item.feed) )">RiskIQ Community</a><br>
																	- <a target="_blank" :href="'http://whois.domaintools.com/'+row.item.rule.substring( 0, row.item.rule.indexOf('.'+row.item.feed) )">DomainTools Whois</a><br>
																	- <a target="_blank" :href="'https://www.robtex.com/dns-lookup/'+row.item.rule.substring( 0, row.item.rule.indexOf('.'+row.item.feed) )">Robtex</a><br>
                                  - <a target="_blank" :href="'https://apility.io/search/'+row.item.rule.substring( 0, row.item.rule.indexOf('.'+row.item.feed) )">Apility.io</a><br>
                                  - <a target="_blank" :href="'https://www.threatminer.org/domain.php?q='+row.item.rule.substring( 0, row.item.rule.indexOf('.'+row.item.feed) )">ThreatMiner</a>

																</b-popover>
																<span :id="'tip-hits-rule'+row.item.rowid">{{row.item.rule}}</span>
															</template>
														</template>
														<template v-slot:cell(rule_type)="row">
															<b-popover title="Actions" :target="'tip-hits-rule_type'+row.item.rowid" triggers="hover">
																<a href="javascript:{}" @click.stop="hits_Filter='rule_type='+row.item.rule_type">Filter by</a>
															</b-popover>
															<span :id="'tip-hits-rule_type'+row.item.rowid">{{row.item.rule_type}}</span>
														</template>
													</b-table>

												</span>
											</template>
										</b-col>
									</b-row>

								</b-card>


							</div>

						<!--End RPZ hits page-->
          </b-tab>

          <b-tab>
						<template slot="title"><i class="fas fa-screwdriver"></i><span class="d-none d-lg-inline" v-bind:class="{ hidden: toggleMenu>0 }">&nbsp;&nbsp;Admin</span></template>
						<!--Admin page href='#/cfg/admin'-->
							<p>
								<div class="v-spacer"></div>
								<b-card no-body>
									<b-tabs card>
										<b-tab title="Assets" active>
											<b-row class='d-none d-sm-flex'>
												<b-col cols="3" lg="3">
													<b-button v-b-tooltip.hover title="Add" variant="outline-secondary" size="sm" @click.stop="addAssetAddr='';addAssetName='';addAssetVendor='';addAssetComment='';addAssetRowID=0;$emit('bv::show::modal', 'mAddAsset')"><i class="fa fa-plus"></i></b-button>
													<b-button v-b-tooltip.hover title="Edit" variant="outline-secondary" size="sm" :disabled="!asset_selected" @click.stop="addAssetAddr=asset_selected.address;addAssetName=asset_selected.name;addAssetVendor=asset_selected.vendor;addAssetComment=asset_selected.comment;addAssetRowID=asset_selected.rowid;$emit('bv::show::modal', 'mAddAsset')"><i class="fa fa-edit"></i></b-button>
													<b-button v-b-tooltip.hover title="Delete" variant="outline-secondary" size="sm" @click.stop="delete_asset(asset_selected,'assets')" :disabled="!asset_selected"><i class="fa fa-trash-alt"></i></b-button>
													<b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl('assets')"><i class="fa fa-sync"></i></b-button>
												</b-col>

												<b-col cols="3" lg="3">

												</b-col>
												<b-col cols="6" lg="6">
													<b-form-group label-cols-md="4" label-size="sm" >
														<b-input-group>
															<b-input-group-text slot="prepend" size="sm"><i class="fas fa-filter fa-fw" size="sm"></i></b-input-group-text>
															<b-form-input v-model="assets_Filter" placeholder="Type to search" size="sm"></b-form-input>
															<b-button size="sm" slot="append" :disabled="!assets_Filter" @click="assets_Filter = ''">Clear</b-button>
														</b-input-group>
													</b-form-group>
												</b-col>
											</b-row>
											<b-row>
												<b-col cols="12" lg="12">
													<b-table id="assets"  :sticky-header="`${logs_height}px`" :sort-icon-left='true' no-border-collapse striped hover small :no-provider-paging=true :no-provider-sorting=true :no-provider-filtering=true  :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=assets'" :fields="assets_fields" :filter="assets_Filter">
														<template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
														<template v-slot:cell(rowid)="row">
															<b-form-checkbox :value="row.item" :name="'asset'+row.item.rowid" v-model="asset_selected" />
														</template>
														<template v-slot:cell(address)="row">
															<b-popover title="Actions" :target="'tip-assets'+row.item.address" triggers="hover">
																<a href="javascript:{}" @click.stop="qlogs_Filter=row.item.address;cfgTab=1;">Show queries</a><br>
																<a href="javascript:{}" @click.stop="hits_Filter=row.item.address;cfgTab=2;">Show hits</a>
															</b-popover>
															<span :id="'tip-assets'+row.item.address">{{row.item.address}}</span>
														</template>
														<template v-slot:cell(name)="row">
															<b-popover title="Actions" :target="'tip-assets_name'+row.item.rowid" triggers="hover">
																<a href="javascript:{}" @click.stop="qlogs_Filter=row.item.name;cfgTab=1;">Show queries</a><br>
																<a href="javascript:{}" @click.stop="hits_Filter=row.item.name;cfgTab=2;">Show hits</a>
															</b-popover>
															<span :id="'tip-assets_name'+row.item.rowid">{{row.item.name}}</span>
														</template>
														<template v-slot:cell(vendor)="row">
															<b-popover title="Actions" :target="'tip-assets_vendor'+row.item.rowid" triggers="hover">
																<a href="javascript:{}" @click.stop="qlogs_Filter=row.item.vendor;cfgTab=1;">Show queries</a><br>
																<a href="javascript:{}" @click.stop="hits_Filter=row.item.vendor;cfgTab=2;">Show hits</a>
															</b-popover>
															<span :id="'tip-assets_vendor'+row.item.rowid">{{row.item.vendor}}</span>
														</template>
													</b-table>
												</b-col>
											</b-row>
										</b-tab>
										<b-tab title="RPZ Feeds" lazy>
											<b-row>
												<b-col cols="12" lg="12">
													<b-table id="rpz_feeds"  :sticky-header="`${logs_height}px`" :sort-icon-left='true' no-border-collapse striped hover small :no-provider-paging=true :no-provider-sorting=true :no-provider-filtering=true  :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=rpz_feeds'" :fields="rpz_feeds_fields" :filter="rpz_feeds_Filter">
														<template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
														<template v-slot:cell(act)="row">
															<b-button v-b-tooltip.hover title="Retransfer" variant="outline-secondary" size="sm" @click.stop="retransferRPZ(row)"><i class="fas fa-redo"></i></b-button>
														</template>
													</b-table>
												</b-col>
											</b-row>
										</b-tab>
										<b-tab title="Block" lazy>

											<b-row class='d-none d-sm-flex'>
												<b-col cols="3" lg="3">
													<b-button v-b-tooltip.hover title="Add" variant="outline-secondary" size="sm" @click.stop="addIOC='';addIOCtype='bl';addIOCcomment='';addBLRowID=0;addIOCactive=true;addIOCsubd=true;$emit('bv::show::modal', 'mAddIOC')"><i class="fa fa-plus"></i></b-button>
													<b-button v-b-tooltip.hover title="Edit" variant="outline-secondary" size="sm" :disabled="!bl_selected" @click.stop="addIOC=bl_selected.ioc;addIOCtype='bl';addIOCcomment=bl_selected.comment;addBLRowID=bl_selected.rowid;addIOCactive=(bl_selected.active===1);addIOCsubd=(bl_selected.subdomains===1);$emit('bv::show::modal', 'mAddIOC')"><i class="fa fa-edit"></i></b-button>
													<b-button v-b-tooltip.hover title="Delete" variant="outline-secondary" size="sm" @click.stop="delete_asset(bl_selected,'blacklist')" :disabled="!bl_selected"><i class="fa fa-trash-alt"></i></b-button>
													<b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl('blacklist')"><i class="fa fa-sync"></i></b-button>
												</b-col>

												<b-col cols="3" lg="3">

												</b-col>
												<b-col cols="6" lg="6">
													<b-form-group label-cols-md="4" label-size="sm" >
														<b-input-group>
															<b-input-group-text slot="prepend" size="sm"><i class="fas fa-filter fa-fw" size="sm"></i></b-input-group-text>
															<b-form-input v-model="bl_Filter" placeholder="Type to search" size="sm"></b-form-input>
															<b-button size="sm" slot="append" :disabled="!bl_Filter" @click="bl_Filter = ''">Clear</b-button>
														</b-input-group>
													</b-form-group>
												</b-col>
											</b-row>
											<b-row>
												<b-col cols="12" lg="12">
													<b-table id="blacklist" ref="blacklist" :sticky-header="`${logs_height}px`" :sort-icon-left='true' no-border-collapse responsive striped hover small :no-provider-paging=true :no-provider-sorting=true :no-provider-filtering=true  :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=blacklist'" :fields="lists_fields" :filter="bl_Filter">
														<template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
														<template v-slot:cell(rowid)="row">
															<b-form-checkbox :value="row.item" :name="'bl'+row.item.rowid" v-model="bl_selected" />
														</template>
														<template v-slot:cell(subdomains)="row">
															<span  @click="toggle_ioc('blacklist',row.item.rowid,'subdomains')">
																<div v-if="row.item.subdomains == '1'"><i class="fas fa-toggle-on fa-lg"></i></div><div v-else><i class="fas fa-toggle-off fa-lg"></i></div>
															</span>
														</template>
														<template v-slot:cell(active)="row">
															<span  @click="toggle_ioc('blacklist',row.item.rowid,'active')">
																<div v-if="row.item.active == '1'"><i class="fas fa-toggle-on fa-lg"></i></div><div v-else><i class="fas fa-toggle-off fa-lg"></i></div>
															</span>
														</template>
														<template v-slot:cell(ioc)="row">
															<b-popover title="Actions" :target="'tip-blacklist'+row.item.ioc" triggers="hover">
																<a href="javascript:{}" @click.stop="qlogs_Filter=row.item.ioc;cfgTab=1;">Show queries</a><br>
																<a href="javascript:{}" @click.stop="hits_Filter=row.item.ioc;cfgTab=2;">Show hits</a>
															</b-popover>
															<span :id="'tip-blacklist'+row.item.ioc">{{row.item.ioc}}</span>
														</template>
													</b-table>
												</b-col>
											</b-row>

										</b-tab>
										<b-tab title="Allow" lazy>


											<b-row class='d-none d-sm-flex'>
												<b-col cols="3" lg="3">
													<b-button v-b-tooltip.hover title="Add" variant="outline-secondary" size="sm" @click.stop="addIOC='';addIOCtype='wl';addIOCcomment='';addBLRowID=0;addIOCactive=true;addIOCsubd=true;$emit('bv::show::modal', 'mAddIOC')"><i class="fa fa-plus"></i></b-button>
													<b-button v-b-tooltip.hover title="Edit" variant="outline-secondary" size="sm" :disabled="!wl_selected" @click.stop="addIOC=wl_selected.ioc;addIOCtype='wl';addIOCcomment=wl_selected.comment;addBLRowID=wl_selected.rowid;addIOCactive=(wl_selected.active===1);addIOCsubd=(wl_selected.subdomains===1);$emit('bv::show::modal', 'mAddIOC')"><i class="fa fa-edit"></i></b-button>
													<b-button v-b-tooltip.hover title="Delete" variant="outline-secondary" size="sm" @click.stop="delete_asset(wl_selected,'whitelist')" :disabled="!wl_selected"><i class="fa fa-trash-alt"></i></b-button>
													<b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl('whitelist')"><i class="fa fa-sync"></i></b-button>
												</b-col>

												<b-col cols="3" lg="3">

												</b-col>
												<b-col cols="6" lg="6">
													<b-form-group label-cols-md="4" label-size="sm" >
														<b-input-group>
															<b-input-group-text slot="prepend" size="sm"><i class="fas fa-filter fa-fw" size="sm"></i></b-input-group-text>
															<b-form-input v-model="wl_Filter" placeholder="Type to search" size="sm" debounce="300"></b-form-input>
															<b-button size="sm" slot="append" :disabled="!wl_Filter" @click="wl_Filter = ''">Clear</b-button>
														</b-input-group>
													</b-form-group>
												</b-col>
											</b-row>
											<b-row>
												<b-col cols="12" lg="12">
													<b-table id="whitelist" ref="whitelist" :sticky-header="`${logs_height}px`" :sort-icon-left='true' no-border-collapse striped hover small responsive :no-provider-paging=true :no-provider-sorting=true :no-provider-filtering=true  :items="get_tables" :api-url="'/rpi_admin/rpidata.php?req=whitelist'" :fields="lists_fields" :filter="wl_Filter">
														<template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
														<template v-slot:cell(rowid)="row">
															<b-form-checkbox :value="row.item" :name="'wl'+row.item.rowid" v-model="wl_selected" />
														</template>
														<template v-slot:cell(subdomains)="row">
															<span  @click="toggle_ioc('whitelist',row.item.rowid,'subdomains')">
																<div v-if="row.item.subdomains == '1'"><i class="fas fa-toggle-on fa-lg"></i></div><div v-else><i class="fas fa-toggle-off fa-lg"></i></div>
															</span>
														</template>
														<template v-slot:cell(active)="row">
															<span  @click="toggle_ioc('whitelist',row.item.rowid,'active')">
																<div v-if="row.item.active == '1'"><i class="fas fa-toggle-on fa-lg"></i></div><div v-else><i class="fas fa-toggle-off fa-lg"></i></div>
															</span>
														</template>
														<template v-slot:cell(ioc)="row">
															<b-popover title="Actions" :target="'tip-whitelist'+row.item.ioc" triggers="hover">
																<a href="javascript:{}" @click.stop="qlogs_Filter=row.item.ioc;cfgTab=1;">Show queries</a><br>
																<a href="javascript:{}" @click.stop="hits_Filter=row.item.ioc;cfgTab=2;">Show hits</a>
															</b-popover>
															<span :id="'tip-whitelist'+row.item.ioc">{{row.item.ioc}}</span>
														</template>
													</b-table>
												</b-col>
											</b-row>

										</b-tab>
										<b-tab title="Settings" lazy>
											<b-row>
												<b-col cols="12" lg="7">
													<h4>Data statistics and retention</h4>
													<b-table id="tbl_retention" :busy="db_stats_busy" no-border-collapse responsive striped hover small :items="retention" :fields="retention_fields">
														<template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
														<template v-slot:cell(5)="row">
															<b-form-input :ref="'ret_'+row.item[0]" min=1 max=1825 type="number" size="sm" :value="row.item[5]" v-b-tooltip.hover title="days"></b-form-input>
														</template>
													</b-table>
												</b-col>
												<b-col cols="12" lg="5">
													<h4>Miscellaneous</h4>
													<hr class="mt-0">
													<b-form-checkbox v-model="assets_autocreate" switch>Automatically create assets</b-form-checkbox>
													<div class="v-spacer"></div>
													<b-form inline class="mw350">
														<label for="assets_by">Track assets by&nbsp;&nbsp;&nbsp;</label>
														<b-form-select id="assets_by" v-model="assets_by" size="sm">
															<b-form-select-option value="mac">MAC Address</b-form-select-option>
															<b-form-select-option value="ip">IP Address</b-form-select-option>
														</b-form-select><br><br>
														<label for="dashboard_topx">Dashboard show Top &nbsp;&nbsp;&nbsp;</label>
														<b-form-input id="dashboard_topx" min=1 max=200 type="number" size="sm" v-model="dashboard_topx"></b-form-input>
													</b-form>
												</b-col>
											</b-row>
											<b-row>
												<b-col cols="12">
													<b-button size="sm" @click="setSettings">Save</b-button>
												</b-col>
											</b-row>
										</b-tab>
										<b-tab title="Tools" lazy>
											<b-card-group deck>
												<b-card header="CA root certificate" body-class="p-2">
													<p>CA root certificate is used to sign all SSL certificates. Install certificate to you browser/OS to avoid displaing certificate error message before the block page.</p>
													<a :href="'/rpi_admin/rpidata.php?req=download&file=CA'" class="btn btn-secondary btn-sm"><i class="fa fa-download"></i>&nbsp;&nbsp;Download</a>
												</b-card>
												<b-card header="Database" body-class="p-2">
													<p>SQLite database stores all DNS query and RPZ logs, application settings. If you need to keep the data - periodically backup the database.</p>
													<a :href="'/rpi_admin/rpidata.php?req=download&file=DB'" class="btn btn-secondary btn-sm"><i class="fa fa-download"></i>&nbsp;&nbsp;Download</a>
													<b-button v-b-tooltip.hover title="Import" variant="secondary" size="sm" @click.stop="upload_file=null;db_import_type=['assets','bl','wl','q_raw','h_raw','q_5m','h_5m','q_1h','h_1h','q_1d','h_1d'];$emit('bv::show::modal', 'mImportDB')"><i class="fa fa-upload"></i>&nbsp;&nbsp;Import</b-button>
												</b-card>
												<b-card header="ISC Bind logs files" body-class="p-2">
													<p>Bind log files contain internal DNS server log messages, raw DNS query log and RPZ log messages. bind_queries.log contains DNS query and rpz logs.</p>
													<b-input-group>
														<a :href="'/rpi_admin/rpidata.php?req=download&file=bind.log'" class="btn btn-secondary btn-sm"><i class="fa fa-download"></i>&nbsp;&nbsp;bind.log</a>&nbsp;&nbsp;&nbsp;
														<a :href="'/rpi_admin/rpidata.php?req=download&file=bind_queries.log'" class="btn btn-secondary btn-sm"><i class="fa fa-download"></i>&nbsp;&nbsp;bind_queries.log</a>&nbsp;&nbsp;&nbsp;
														<a :href="'/rpi_admin/rpidata.php?req=download&file=bind_rpz.log'" class="btn btn-secondary btn-sm"><i class="fa fa-download"></i>&nbsp;&nbsp;bind_rpz.log</a>
													</b-input-group>
												</b-card>
											</b-card-group>
										</b-tab>
									</b-tabs>
								</b-card>
							</div>
						<!--End Admin page-->
          </b-tab>


          <b-tab lazy>
						<template slot="title"><i class="fas fa-hands-helping"></i><span class="d-none d-lg-inline" v-bind:class="{ hidden: toggleMenu>0 }">&nbsp;&nbsp;Help</span></template>
						<!--Help page href='#/cfg/help'-->

						<!--End Help page-->
          </b-tab>

      </b-tabs>
    </b-container>


<!--        -->
<!-- Modals -->
<!--        -->

<!-- add Asset -->
	 <b-modal centered title="Asset" id="mAddAsset" ref="refAddAsset" body-class="text-center pt-0 pb-0" ok-title="Add" @ok="add_asset($event)" v-cloak>
		 <span class='text-center'>
			<b-container fluid>
				<b-row class="pb-1">
					<b-col md="12" class="p-0">
						<b-input v-model.trim="addAssetAddr" placeholder="Enter <?=$AddressType?> address"  v-b-tooltip.hover title="<?=$AddressType?> Address" />
					</b-col>
				</b-row>
				<b-row class="pb-1">
					<b-col md="12" class="p-0">
						<b-input v-model.trim="addAssetName" placeholder="Enter Name"  v-b-tooltip.hover title="Name" />
					</b-col>
				</b-row>
				<b-row class="pb-1">
					<b-col md="12" class="p-0">
						<b-input v-model.trim="addAssetVendor" placeholder="Enter Vendor"  v-b-tooltip.hover title="Vendor" />
					</b-col>
				</b-row>
				<b-row>
					<b-col md="12" class="p-0">
						<b-textarea rows="3" max-rows="6" maxlength="250" v-model.trim="addAssetComment" placeholder="Commentary"  v-b-tooltip.hover title="Commentary" />
					</b-col>
				</b-row>
			</b-container>

		 </span>
	 </b-modal>

<!-- add IOC -->
	 <b-modal centered title="Add Indicator" id="mAddIOC" ref="refAddIOC" body-class="text-center pt-0 pb-0" ok-title="Add" @ok="add_ioc($event)" v-cloak>
		 <span class='text-center'>
			<b-container fluid>
				<b-row class="pb-1">
					<b-col md="12" class="p-0">
						<b-input v-model.trim="addIOC" placeholder="Enter IOC"  v-b-tooltip.hover title="IOC" />
					</b-col>
				</b-row>
					<b-row class="pb-1">
					<b-col md="12" class="p-0 text-left">
						<b-form-checkbox v-model="addIOCsubd" switch size="lg">&nbsp;Include subdomains</b-form-checkbox>
					</b-col>
				</b-row>
				<b-row>
					<b-col md="12" class="p-0">
						<b-textarea rows="3" max-rows="6" maxlength="250" v-model.trim="addIOCcomment" placeholder="Commentary"  v-b-tooltip.hover title="Commentary" />
					</b-col>
				</b-row>

				<b-row class="pb-1">
					<b-col md="12" class="p-0 text-left">
						<b-form-checkbox v-model="addIOCactive" switch size="lg">&nbsp;Active</b-form-checkbox>
					</b-col>
				</b-row>
			</b-container>

		 </span>
	 </b-modal>

	 <!-- Import DB -->
	 	 <b-modal centered title="Import DB" id="mImportDB" ref="refImportDB" body-class="text-center pt-0 pb-0" ok-title="Import" @ok="import_db($event)" v-cloak>
	 		 <span class='text-center'>
	 			<b-container fluid>
	 				<b-row class="pb-2">
	 					<b-col md="12" class="p-0">
							<b-form-file v-model="upload_file" accept=".sqlite, .gzip, .zip" :state="!(upload_file==null)" placeholder="Choose a file or drop it here..." drop-placeholder="Drop file here..."></b-form-file>
	 					</b-col>
	 				</b-row>
	 				<b-row class="pb-1">
	 					<b-col md="12" class="p-0">
							<b-form-group class="text-left">
					      <b-form-checkbox-group id="dbImportType" v-model="db_import_type" name="dbImportType">
									<b-row class="pb-1">
										<b-col md="4" class="">
							        <b-form-checkbox value="assets">Assets</b-form-checkbox>
										</b-col>
										<b-col md="4" class="p-0">
							        <b-form-checkbox value="bl">Block</b-form-checkbox>
										</b-col>
										<b-col md="4" class="p-0">
							        <b-form-checkbox value="wl">Allow</b-form-checkbox><br>
										</b-col>
									</b-row>
									<b-row class="pb-1">
										<b-col md="6" class="">
					        		<b-form-checkbox value="q_raw">Query logs - Raw</b-form-checkbox>
										</b-col>
										<b-col md="6" class="p-0">
											<b-form-checkbox value="h_raw">RPZ hits logs - Raw</b-form-checkbox><br>
										</b-col>
									</b-row>
									<b-row class="pb-1">
										<b-col md="6" class="">
											<b-form-checkbox value="q_5m">Query logs - 5m</b-form-checkbox>
										</b-col>
										<b-col md="6" class="p-0">
											<b-form-checkbox value="h_5m">RPZ hits logs - 5m</b-form-checkbox><br>
										</b-col>
									</b-row>
									<b-row class="pb-1">
										<b-col md="6" class="">
											<b-form-checkbox value="q_1h">Query logs - 1h</b-form-checkbox>
										</b-col>
										<b-col md="6" class="p-0">
											<b-form-checkbox value="h_1h">RPZ hits logs - 1h</b-form-checkbox><br>
										</b-col>
									</b-row>
									<b-row class="pb-0">
										<b-col md="6" class="">
											<b-form-checkbox value="q_1d">Query logs - 1d</b-form-checkbox>
										</b-col>
										<b-col md="6" class="p-0">
											<b-form-checkbox value="h_1d">RPZ hits logs - 1d</b-form-checkbox>
										</b-col>
									</b-row>
					      </b-form-checkbox-group>
					    </b-form-group>

	 					</b-col>
	 				</b-row>
	 			</b-container>
	 		 </span>
	 	 </b-modal>
		 <!-- Upload Progress -->
	 	 <b-modal centered title="Upload progress" id="mUploadPr" :ok-disabled="fUpInd" ref="refUploadPr" body-class="text-center pt-0 pb-0" no-close-on-esc no-close-on-backdrop ok-only ok-title="Cancel" ok-variant="secondary" v-cloak @ok="cancel_upload($event)">
			 <b-progress v-if="fUpInd" :value="upload_progress" :max="100" height="20px" show-progress animated></b-progress>
			 <span v-if="fImpInd"><b-spinner small type="grow"></b-spinner>&nbsp;&nbsp;Validating...</span>
		 </b-modal>


<!--        -->
<!-- Modals -->
<!--        -->

		</div>

	</div>

	<div class="copyright"><p>Copyright © 2020 Vadim Pavlov</p></div>
<!--
		<script src="https://cdn.jsdelivr.net/npm/vue@latest/dist/vue.js"></script>
		<script src="//unpkg.com/babel-polyfill@latest/dist/polyfill.min.js"></script>
		<script src="//unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.js"></script>
		<script src="//unpkg.com/axios/dist/axios.min.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
		<script src="https://cdn.jsdelivr.net/npm/vue-apexcharts"></script>

		<script src="/rpi_admin/js/vue.js"></script>
-->
		<script src="/rpi_admin/js/vue.min.js"></script>
		<script src="/rpi_admin/js/polyfill.min.js"></script>
		<script src="/rpi_admin/js/bootstrap-vue.min.js"></script>
		<script src="/rpi_admin/js/axios.min.js"></script>
		<script src="/rpi_admin/js/apexcharts"></script>
		<script src="/rpi_admin/js/vue-apexcharts"></script>

		<script src="/rpi_admin/js/rpi_admin.js?<?=$rpiver?>"></script>
	</body>
</html>
