function icmpModule(enabledOptions, ajaxUrls, tzOffset) {
	var _thisModule = this;
	var _enabledOptions = enabledOptions;
    var _ajaxUrls = ajaxUrls;
    var _offset = tzOffset;

	/**
	* Puts the series into an acceptable form for our graph 
	*/
	this.dataFunction = function(min, max, bin, callback, pack) {
        var ajaxUrl, ajaxReqs = {}, completed = false;
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
                    var options = _thisModule.series();
                    
                    if (data.length < 1) {
                        return;
                    }
                    
                    for (var i = 0; i < data.length; i++) {
                        var dp = data[i].data;
                        var time = dp.time * 1000 + _offset;
                        
                        for (var prop in dp.rtt_ms) {
                            var stat = dp.rtt_ms[prop];
                            
                            if (options[prop] == undefined || stat == -1) {
                                continue;
                            }
                            
                            if (!stats[prop]) {
                                stats[prop] = [];
                            }
                            
                            stats[prop].push([time, stat]);
                        }
                    }

                    for (var prop in stats) {
                        $.extend(pack.identity, ajax);
                        
                        var cur = {
                            data: stats[prop], 
                            label: prop, 
                            xGapThresh: bin * 1000,
                            indicateGaps: true,
                            identity: pack.identity,
                            shadowSize: 0
                        };
                    
                        processed.sets[prop] = $.extend(true, {}, cur, options[prop]);
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
        // Take a number from 0..255 (inclusive) and convert to a gradiant.
        var getSimpleColour = function(value) {
            // Colour things from a gradient.
            var value = Math.floor(value);

            if (value>255)
                value=255;

            // if in first half, colour = (2*value, 100%, 0)
            if(value < 128) {
                value = value*2;
                value = value.toString(16); // Convert to hex.
                if(value.length == 1) value = "0"+value; // pad hex
        
                return '#'+value+'ff00';
            }

            // if in second half, colour = (100%, 100%-2*value, 0)
            value = 255-(2*(value-128));
            value = value.toString(16);
            if(value.length == 1) value = "0"+value; // pad hex

            return '#ff'+value+'00';
        }

        var loss = function(value) {
            if (value != 0) value = (Math.log(value * 100)/Math.log(100)) * 255;
            return getSimpleColour(value);
        };

        var lossColorStops = [];
        for (var i = 80; i >= 0; i -= 2) {
            lossColorStops.push([1 - i / 80, loss(i / 100)]);
        }
        
        var boxes = {
			max: {
				color: "#CB4B4B",
				yaxis: 1,
                heatmap: {
                    smooth: true
                }
			},
			min: {
				color: "#9440ED",
				yaxis: 1,
                heatmap: {
                    smooth: true
                }
			},
			mean: {
				color: "#00A8F0",
				yaxis: 1,
                heatmap: {
                    smooth: true
                }
			},
			loss: {
				color: "#4DA74D",
				yaxis: 2,
                heatmap: {
                    valueToColor: loss,
                    colorStops: lossColorStops
                }
			},
			jitter: {
				color: "#C0D800",
				yaxis: 1,
                heatmap: {
                    smooth: true
                }
			}
		};
	
        if (_ajaxUrls.length > 1) boxes.loss.yaxis = 1;

		return boxes;
	};
	
	/**
	* Generates the input fields for the graph
	*/
	this.inputFields = function() {
		var boxes = {};
		var series = this.series();
	
		// set the default stat to be the mean
		for (var i = 0; i < _enabledOptions.length; i++) {
			if (_enabledOptions[i] == "latency")
				_enabledOptions[i] = "mean";
		}
		
		for (var stat in series) {
			s = series[stat];
			var box;
			var section = "Latency";
			
			if (stat == "loss") {
				section = "Other";
			}
			
			if ($.inArray(stat, _enabledOptions) != -1) {
				box = $('<input checked="" type="checkbox" name="' + stat + '">');
			} else {
				box = $('<input type="checkbox" name="' + stat + '">');
			}
			
			if (!boxes[section]) {
				boxes[section] = [];
			}
			
			boxes[section].push(box);
		}
			
		// return all the boxes 
		return boxes;
	};
}
