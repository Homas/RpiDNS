/*
(c) Vadim Pavlov 2020
RpiDNS powered by https://ioc2rpz.net
*/

const gColors = ['#008FFB', '#00E396', '#FEB019', '#FF4560', '#775DD0',
								 '#3F51B5', '#03A9F4', '#4CAF50', '#F9CE1D', '#FF9800',
								 '#33B2DF', '#546E7A', '#D4526E', '#13D8AA', '#A5978B',
								 '#4ECDC4', '#C7F464', '#81D4FA', '#546E7A', '#FD6A6A',
								 '#2B908F', '#F9A3A4', '#90EE7E', '#FA4443', '#69D2E7',
								 '#449DD1', '#F86624', '#EA3546', '#662E9B', '#C5D86D',
								 '#D7263D', '#1B998B', '#2E294E', '#F46036', '#E2C044',
								 '#662E9B', '#F86624', '#F9C80E', '#EA3546', '#43BCCD',
								 '#5C4742', '#A5978B', '#8D5B4C', '#5A2A27', '#C4BBAF',
								 '#A300D6', '#7D02EB', '#5653FE', '#2983FF', '#00B1F2'];

const io2c_app = new Vue({
  el: "#app",
  components: {
		apexchart: VueApexCharts,
  },
  data: {
		toggleMenu: 0, // show/hide menu
		cfgTab: 0,
		windowInnerWidth: 800,
		logs_height:150, //logs per page
		logs_updatetime:0, //last time the logs were updated
		hits_cp:1,
		hits_Filter:'', //RPZ hits filter
		hits_nrows:0,
		hits_pp:100, //per page
		qlogs_cp:1,
		qlogs_Filter:'', //Query logs filter
		qlogs_nrows:0,
		qlogs_pp:100, //per page

		qlogs_period:'30m',
		hits_period:'30m',
		dash_period:'30m',
		

		//hits_pFilter:'', //RPZ hits previous filter
		//hits_feventts:0,
		//hits_leventts:0,
		//hits_psort:['dtz','desc'],
		//hits_pcp:1,
		
    qlogs_fields: [
        { key: 'dtz', label: 'Local Time', sortable: true, formatter: (value) => { var date = new Date(value); return date.toLocaleString(); }},
        { key: 'cname', label: 'Client', sortable: true, 'tdClass':'mw200 d-none d-sm-table-cell', 'thClass': 'd-none d-sm-table-cell'},
        { key: 'server', label: 'Server', sortable: true,  'tdClass':'mw200 d-none d-lg-table-cell', 'thClass': 'd-none d-lg-table-cell'},
//        { key: 'mac', label: 'MAC', sortable: true, 'tdClass':'mw150 d-none d-sm-table-cell', 'thClass': 'd-none d-sm-table-cell'},
        { key: 'fqdn', label: 'Request', sortable: true, 'tdClass':'mw250'},
        { key: 'type', label: 'Type', sortable: true},
        { key: 'class', label: 'Class', sortable: true, 'tdClass':'d-none d-xl-table-cell', 'thClass': 'd-none d-xl-table-cell'},
        { key: 'options', label: 'Options', sortable: true, 'tdClass':'d-none d-xl-table-cell', 'thClass': 'd-none d-xl-table-cell'},
        { key: 'cnt', label: 'Count', sortable: true},
        { key: 'action', label: 'Action', sortable: true},
    ],		

    hits_fields: [
        { key: 'dtz', label: 'Local Time', sortable: true,   formatter: (value) => { var date = new Date(value); return date.toLocaleString(); }},
        { key: 'cname', label: 'Client', sortable: true, 'tdClass':'mw200 d-none d-sm-table-cell', 'thClass': 'd-none d-sm-table-cell'},
 //       { key: 'mac', label: 'MAC', sortable: true, 'tdClass':'mw150 d-none d-sm-table-cell', 'thClass': 'd-none d-sm-table-cell'},
        { key: 'fqdn', label: 'Request', sortable: true, 'tdClass':'mw200'},
        { key: 'action', label: 'Action', sortable: true, 'tdClass':'d-none d-lg-table-cell', 'thClass': 'd-none d-lg-table-cell'},
        { key: 'rule', label: 'Rule', sortable: true, 'tdClass':'mw300 d-none d-lg-table-cell', 'thClass': 'd-none d-lg-table-cell'},
	      { key: 'rule_type', label: 'Type', sortable: true, 'tdClass':'d-none d-lg-table-cell', 'thClass': 'd-none d-lg-table-cell'},
//        { key: 'feed', label: 'Feed', sortable: true},
        { key: 'cnt', label: 'Count', sortable: true},
    ],
		
		query_ltype:'logs',
		hits_ltype:'logs',
		
		dash_stats_fields:[
			{ key: 'fname', label: 'Name', 'tdClass':'mw350 mouseoverpointer'},
			{ key: 'cnt', label: 'Count'},			
		],

		period_options: [
			{ text: '30m', value: '30m' },
			{ text: '1h', value: '1h' },
			{ text: '1d', value: '1d'  },
			{ text: '1w', value: '1w'  },
			{ text: '30d', value: '30d' },
			{ text: 'custom', value: 'custom', disabled: true },
		],

		qperiod_options: [
			{ text: '30m', value: '30m' },
			{ text: '1h', value: '1h' },
			{ text: '1d', value: '1d'  },
			{ text: '1w', value: '1w'  },
			{ text: '30d', value: '30d' },
			{ text: 'custom', value: 'custom', disabled: true },
		],


			qps_series: [],
      qps_series_sample: [
				{name: 'qps', data: [[1564027200000,50],[1564030800000,100],[1564034400000,150],[1564038000000,100]]},
				{name: 'hits', data: [[1564027200000,10],[1564030800000,50],[1564034400000,50],[1564038000000,30]]},
			],
			
			qps_options: {
				colors: ['#008FFB','#FA4443'],
        chart: {
					id: 'qps-stats',
				//	stacked: true,
				},
				dataLabels: {
            enabled: false
        },
				xaxis: {
					type: 'datetime',
					labels: {datetimeUTC: false},
				},
				tooltip:{
					x:{
						format: 'dd MMM yyyy H:mm',
					}
				},
				yaxis: {
						//logarithmic: true,
						min: 0,
				},
				fill: {
					type: 'gradient',
					gradient: {
						opacityFrom: 0.6,
						opacityTo: 0.8,
					}
          },

      },
			retention:[],
			assets_by:"mac",
			assets_autocreate:true,
			dashboard_topx:100,
			db_stats_busy: false,
			retention_fields: [
				{ key: '0', label: 'Table', },
				{ key: '1', label: 'Size',  'tdClass':'width100 d-none d-md-table-cell', 'thClass': 'd-none d-md-table-cell', formatter: (value) => { return value<1024?value+' b':value<1024*1024?Math.round(value/1024/1024*100)/100+' Kb':value<1024*1024*1024?Math.round(value/1024/1024*100)/100+' Mb':Math.round(value/1024/1024/1024*100)/100+' Gb'} },
				{ key: '2', label: 'Rows',  'tdClass':'width100 d-none d-md-table-cell', 'thClass': 'd-none d-md-table-cell', },
        { key: '3', label: 'From',  'tdClass':'d-none d-md-table-cell', 'thClass': 'd-none d-md-table-cell',   formatter: (value) => { var date = new Date(value); return date.toLocaleString(); }},
        { key: '4', label: 'To',  'tdClass':'d-none d-md-table-cell', 'thClass': 'd-none d-md-table-cell',   formatter: (value) => { var date = new Date(value); return date.toLocaleString(); }},
				{ key: '5', label: 'Retention', },
	
			],

    assets_fields: [
			{ key: 'rowid', label: '',  'tdClass':'width050 d-none d-sm-table-cell', 'thClass': 'd-none d-sm-table-cell' },
			{ key: 'address', label: 'Address', sortable: true, 'tdClass':'mw150 d-none d-sm-table-cell', 'thClass': 'd-none d-sm-table-cell'},
			{ key: 'name', label: 'Name', sortable: true, 'tdClass':'mw200'},
			{ key: 'vendor', label: 'Vendor', sortable: true, 'tdClass':'mw150 d-none d-md-table-cell', 'thClass': 'd-none d-md-table-cell'},
			{ key: 'dtz', label: 'Added', sortable: true,   formatter: (value) => { var date = new Date(value); return date.toLocaleString(); }, 'tdClass':'mw150 d-none d-md-table-cell', 'thClass': 'd-none d-md-table-cell'},
			{ key: 'comment', label: 'Comment', sortable: true, 'tdClass':'mw150 d-none d-md-table-cell', 'thClass': 'd-none d-md-table-cell'},
    ],
		assets_Filter:'', //assets filter
		asset_selected:0,
		addAssetAddr:'',
		addAssetName:'',
		addAssetVendor:'',
		addAssetComment:'',
		addAssetRowID:0,
		addIOC:'',
		addIOCtype:'',
		addIOCcomment:'',
		addIOCactive: true,
		addIOCsubd: true,
		addBLRowID:0,
		bl_Filter:'', //blacklist filter
		bl_selected:0,		
		wl_Filter:'', //whitelist filter
		wl_selected:0,		
    lists_fields: [
			{ key: 'rowid', label: '',  'tdClass':'width050 d-none d-md-table-cell', 'thClass': 'd-none d-md-table-cell' },
			{ key: 'ioc', label: 'Domain/IP', sortable: true, 'tdClass':'mw150'},
			{ key: 'dtz', label: 'Added', sortable: true,  'tdClass':'width250 d-none d-md-table-cell', 'thClass': 'd-none d-md-table-cell',   formatter: (value) => { var date = new Date(value); return date.toLocaleString(); }},
			{ key: 'active', label: 'Active',  'tdClass':'width050 d-none d-md-table-cell', 'thClass': 'd-none d-md-table-cell' },
			{ key: 'subdomains', label: '*.',  'tdClass':'width050 d-none d-md-table-cell', 'thClass': 'd-none d-md-table-cell' },
			{ key: 'comment', label: 'Comment', sortable: true, 'tdClass':'mw150 d-none d-lg-table-cell', 'thClass': 'd-none d-lg-table-cell'},
    ],
	},

  mounted: function () {
    if (window.location.hash) {
      var a=window.location.hash.split(/#|\//).filter(String);
      switch (a[0]){
        case "i2r":
          this.cfgTab=parseInt(a[1]);        
      };
			if (window.localStorage.getItem('toggleMenu')){
					this.toggleMenu=parseInt(window.localStorage.getItem('toggleMenu'));
			}
    };
		update_window_size(this);		
    this.$nextTick(() => {
      window.addEventListener('resize', () => {update_window_size(this);});
    });
		this.refreshDashQPS();
		this.getSettings();
	},
	
  methods: {	
    changeTab: function(tab){
      history.pushState(null, null, '#i2r/'+tab);
			this.cfgTab=tab;
//      if (this.$refs.i2r.$children[tab].$attrs.table) this.$root.$emit('bv::refresh::table', this.$refs.i2r.$children[tab].$attrs.table);
    },

    get_tables (obj) {
			let doc=this;
			let URL=obj.apiUrl;
      let promise = axios.get(obj.apiUrl+'&sortBy='+obj.sortBy+'&sortDesc='+obj.sortDesc)
      return promise.then((data) => {
        items = data.data.data;
				//items[0].rowid items[items.length-1].rowid
				if (/DOCTYPE html/.test(items))	window.location.reload(false);
				if (/=hits_raw/.test(URL)) doc.hits_nrows=parseInt(data.data.records);
				if (/=queries_raw/.test(URL)) doc.qlogs_nrows=parseInt(data.data.records);
				return(items);
      }).catch(error => {
				if (/=hits_raw/.test(URL)) doc.hits_nrows=0;
				if (/=queries_raw/.test(URL)) doc.qlogs_nrows=0;
        return []
      })
    },
		
    refreshTbl(tbl){
			if ((tbl=='qlogs'||tbl=='hits') && Date.now()-this.logs_updatetime>60*1000) { //update once a minute 
	      this.$root.$emit('bv::refresh::table', tbl);
				this.logs_updatetime=Date.now();
			}else{
	      this.$root.$emit('bv::refresh::table', tbl);				
			};
    },
		
    refreshDashQPS(){
			let doc=this;
			axios.get('/rpi_admin/rpidata.php?req=qps_chart&period='+this.dash_period).then((data) => {doc.$root.qps_series=data.data});
		},

    refreshDash(){
			this.refreshDashQPS();
			this.$root.$emit('bv::refresh::table', 'dash_topX_req');
			this.$root.$emit('bv::refresh::table', 'dash_topX_req_type');
			this.$root.$emit('bv::refresh::table', 'dash_topX_client');
			this.$root.$emit('bv::refresh::table', 'dash_topX_breq');
			this.$root.$emit('bv::refresh::table', 'dash_topX_bclient');
			this.$root.$emit('bv::refresh::table', 'dash_topX_feeds');
		},

    getSettings(){
			let doc=this;
			this.db_stats_busy=true;
			axios.get('/rpi_admin/rpidata.php?req=RPIsettings').then((data) => {
				doc.db_stats_busy=false;
				doc.retention=data.data.retention;
				doc.assets_autocreate=(data.data.assets_autocreate==='1');
				doc.assets_by=data.data.assets_by;
				doc.dashboard_topx=parseInt(data.data.dashboard_topx);
			});
		},
		
		setSettings(){
			let doc=this;
			data={dash_topx:this.dashboard_topx, assets_by:this.assets_by, assets_autocreate:this.assets_autocreate,
						queries_raw:this.$refs.ret_queries_raw.localValue,queries_5m:this.$refs.ret_queries_5m.localValue,queries_1h:this.$refs.ret_queries_1h.localValue,queries_1d:this.$refs.ret_queries_1d.localValue,
						hits_raw:this.$refs.ret_hits_raw.localValue,hits_5m:this.$refs.ret_hits_5m.localValue,hits_1h:this.$refs.ret_hits_1h.localValue,hits_1d:this.$refs.ret_hits_1d.localValue};
			axios.put('/rpi_admin/rpidata.php?req=RPIsettings',data).then((data) => {
				if (data.data.status!="success") doc.showInfo(data.data.reason,3); else doc.showInfo("Settings were saved",3);		
			}).catch(error => {
				doc.showInfo('Unknown error!!!',3);
			});
		},
		
		//dashDrilldown(tab,item){
		//	this.qlogs_Filter=item.name;
		//	this.cfgTab=tab;
		//},
		
		add_asset($ev){
			let doc=this;
			data={id:this.addAssetRowID, name: this.addAssetName, address: this.addAssetAddr, vendor: this.addAssetVendor, comment: this.addAssetComment};
			if (this.addAssetRowID==0) promise = axios.post('/rpi_admin/rpidata.php?req=assets',data); else promise = axios.put('/rpi_admin/rpidata.php?req=assets',data);					
			var items=promise.then((data) => {
				if (data.data.status=="success") {
					doc.$root.$emit('bv::refresh::table', 'assets');
				}else{
					doc.showInfo(data.data.reason,3);					
				}
			}).catch(error => {
				doc.showInfo('Unknown error!!!',3);
			})
		},
		
		delete_asset(asset,tbl){

			this.$bvModal.msgBoxConfirm('You are about to delete the selected asset. This action is irreversible!', {
				title: 'Please confirm the action',
				size: 'md',
				buttonSize: 'md',
				okVariant: 'danger',
				okTitle: 'YES',
				cancelTitle: 'NO',
				footerClass: 'p-2',
				bodyClass: 'text-center',
				hideHeaderClose: false,
				centered: true
			})
				.then(value => {

					if (value) {
						let doc=this;
						var data={id: asset.rowid};
						let table=tbl;
						let promise = axios.delete('/rpi_admin/rpidata.php?req='+table,{data});
						var items=promise.then((data) => {
							if (data.data.status=="success") {
								doc.$root.$emit('bv::refresh::table', table);
							}else{
								doc.showInfo(data.data.reason,3);						
							}
						}).catch(error => {
							doc.showInfo('Unknown error!!!',3);
						})
		
					};
					
			});
			
		},


		add_ioc($ev){
			let doc=this;
			let $table=this.addIOCtype=='bl'?'blacklist':'whitelist';
			data={id:this.addBLRowID, ioc: this.addIOC, ltype: this.addIOCtype, active: this.addIOCactive, subdomains: this.addIOCsubd, comment: this.addIOCcomment};
			if (this.addBLRowID==0) promise = axios.post('/rpi_admin/rpidata.php?req='+$table,data); else promise = axios.put('/rpi_admin/rpidata.php?req='+$table,data);					
			var items=promise.then((data) => {
				if (data.data.status=="success") {
					doc.$root.$emit('bv::refresh::table', $table);
				}else{
					doc.showInfo(data.data.reason,3);					
				}
			}).catch(error => {
				doc.showInfo('Unknown error!!!',3);
			})
		},
	
    showInfo: function (msg,time) {
			let size='sm';
			if (msg.length>30) size='md';
      var self=this;
			var id = Math.random().toString(36).substring(7);
			this.$bvModal.msgBoxOk(msg, {
				id: 'infoMsgBox'+id,
				size: size,
				buttonSize: 'sm',
				okVariant: 'success',
				headerClass: 'p-2 border-bottom-0',
				footerClass: 'p-2 border-top-0',
				bodyClass: 'font-weight-bold text-center',
				centered: true
			});
      setTimeout(function(){
				self.$bvModal.hide('infoMsgBox'+id)
      }, time * 1000);
    },   
		
	},
	
});

function update_window_size(obj){
	if (obj.$refs  === undefined) {obj = io2c_app;};
	obj.logs_pp = window.innerHeight>500 && window.innerWidth>1000?Math.floor((window.innerHeight -350)/28):5;
	obj.logs_height = window.innerHeight>400?(window.innerHeight - 240):150; //250
	obj.windowInnerWidth = window.innerWidth;	
};