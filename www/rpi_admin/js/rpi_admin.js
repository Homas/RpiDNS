/*
(c) Vadim Pavlov 2020
RpiDNS
*/
const io2c_app = new Vue({
  el: "#app",
  components: {
		apexchart: VueApexCharts,
  },
  data: {
		toggleMenu: 0, // show/hide menu
		cfgTab: 0,
		windowInnerWidth: 800,
		qlogs_Filter:'', //Query logs filter
		hits_Filter:'', //RPZ hits filter
		logs_height:150, //logs per page
		logs_updatetime:0, //last time the logs were updated
		
    qlogs_fields: [
        { key: 'dtz', label: 'Local Time', sortable: true,   formatter: (value) => { var date = new Date(value); return date.toLocaleString(); }},
        { key: 'client_ip', label: 'Client IP', sortable: true },
        { key: 'fqdn', label: 'Request', sortable: true},
        { key: 'type', label: 'Type', sortable: true},
        { key: 'class', label: 'Class', sortable: true},
        { key: 'options', label: 'Options', sortable: true},
        { key: 'server', label: 'Server', sortable: true},
        { key: 'cnt', label: 'Count', sortable: true},
        { key: 'action', label: 'Action', sortable: true},
    ],		

    hits_fields: [
        { key: 'dtz', label: 'Local Time', sortable: true,   formatter: (value) => { var date = new Date(value); return date.toLocaleString(); }},
        { key: 'client_ip', label: 'Client IP', sortable: true },
        { key: 'fqdn', label: 'Request', sortable: true},
        { key: 'action', label: 'Action', sortable: true},
        { key: 'rule', label: 'Rule', sortable: true},
	      { key: 'rule_type', label: 'Type', sortable: true},
        { key: 'feed', label: 'Feed', sortable: true},
        { key: 'cnt', label: 'Count', sortable: true},
    ],			
	},

  mounted: function () {
    if (window.location.hash) {
      var a=window.location.hash.split(/#|\//).filter(String);
      switch (a[0]){
        case "i2r":
          this.cfgTab=parseInt(a[1]);        
      };
    };
		update_window_size(this);		
    this.$nextTick(() => {
      window.addEventListener('resize', () => {update_window_size(this);});
    });
	},
	
  methods: {	
    changeTab: function(tab){
      history.pushState(null, null, '#i2r/'+tab);
      if (this.$refs.i2r.$children[tab].$attrs.table) this.$root.$emit('bv::refresh::table', this.$refs.i2r.$children[tab].$attrs.table);
    },

    get_tables (obj) {
			let doc=this;
      let promise = axios.get(obj.apiUrl)
      return promise.then((data) => {
        items = data.data.data;
				if (/DOCTYPE html/.test(items))	window.location.reload(false);
				return(items);
      }).catch(error => {
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

	},
	
});

function update_window_size(obj){
	if (obj.$refs  === undefined) {obj = io2c_app;};
	obj.logs_pp = window.innerHeight>500 && window.innerWidth>1000?Math.floor((window.innerHeight -350)/28):5;
	obj.logs_height = window.innerHeight>400?(window.innerHeight - 150):150; //250
	obj.windowInnerWidth = window.innerWidth;	
};