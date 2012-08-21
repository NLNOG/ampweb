function dns2Module(enabledOptions, ajaxUrls, tzOffset) {
	var _thisModule = this;
	var _enabledOptions = enabledOptions;
    var _ajaxUrls = ajaxUrls;
    var _offset = tzOffset;
    var _colors = {};
    var _instanceCount = 0;

	/**
	* Puts the series into an acceptable form for our graph 
	*/
	this.dataFunction = function(min, max, bin, callback, pack) {
        var ajaxUrl, ajaxReqs = {};
        min -= _offset / 1000;
        max -= _offset / 1000;
        // make sure that the ajax urls is in array form
        if (!$.isArray(_ajaxUrls)) _ajaxUrls = [ _ajaxUrls ];
	
        $.each(_ajaxUrls, function(index, ajax) {

            ajaxReqs[ajax.url] = $.ajax({
                url: ajax.url + min + '/' + max + '/;api_key=www&stat=all&binsize=' + bin,
                dataType: "json",
                success: function(series) { 
                    ajaxReqs[ajax.url].serviced = true;

                    if (!series || !series.response || !series.response.dataset) {
                        return;
                    }
                    
                    var processed = { sets: {} };
                    var data = series.response.dataset;
                    var stats = {};
                    //var options = _thisModule.series();
                    var completed = false;
                    var colors = ["#00A8F0", "#9440ED", "#CB4B4B"];
                    
                    if (data.length < 1) {
                        return;
                    }

                    for (var i = 0; i < data.length; i++) {
                        for (var instance in data[i]) {
                            if (instance == "NULL") continue;

                            for (var j = 0; j < data[i][instance].length; j++) {
                                var dp = data[i][instance][j].data;
                                var mean = dp.rtt_ms.mean;
                                var time = dp.time * 1000 + _offset;

                                if (mean == -1) continue;

                                if (!stats[instance]) stats[instance] = [];
                                stats[instance].push([time, mean]);
                            }
                        }
                    }

                    var count = 0;
                    for (var instance in stats) {
                        $.extend(pack.identity, ajax);
                        pack.identity.label = ajax.src + " to " + instance.split(".", 1)[0];
                        
                        if (!_colors[instance]) {
                            _colors[instance] = colors[_instanceCount % colors.length];
                            _instanceCount++;
                        }

                        processed.sets[instance] = {
                            data: stats[instance],
                            label: instance,
                            xGapThresh: bin * 1000,
                            identity: pack.identity,
                            shadowSize: 0,
                            color: _colors[instance]
                        };
                        
                        processed.sets[instance] = $.extend(true, {}, processed.sets[instance]);
                    }

                    pack.data[ajax.url] = processed.sets;
                }
            });
        });

        // set an interval to check when all the data has been collected
        var interval = setInterval(function() {
            $.each(ajaxReqs, function(index, val) {
                // find non serviced
                if (val.serviced) {
                    completed = true;
                } else {
                    completed = false;
                    return false; // break
                }
            });

            if (completed) {
                callback.call(pack.context, pack);
                clearInterval(interval);
            }
        }, 500);
	};
	
	/**
	* Sets up the series options.
	* eg. colour, which yaxis to draw on
	*/
	this.series = function() {
	    return null;
    };
	
	/**
	* Generates the input fields for the graph
	*/
	this.inputFields = function() {
	    return null;
    };
}
