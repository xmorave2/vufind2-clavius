function loadVis(facetFields, searchParams, baseURL, zooming) {
    // options for the graph, TODO: make configurable
    var options = {
        series: {
            bars: {
                show: true,
                align: "center",
                fill: true,
                fillColor: "rgb(0,0,0)"
            }
        },
        colors: ["rgba(255,0,0,255)"],
        legend: { noColumns: 2 },
        xaxis: { tickDecimals: 0 },
        yaxis: { min: 0, ticks: [] },
        selection: {mode: "x"},
        grid: { backgroundColor: null /*"#ffffff"*/ }
    };

    // AJAX call
    var url = baseURL + '/AJAX/json?method=getVisData&facetFields=' + encodeURIComponent(facetFields) + '&' + searchParams;
    //alert(url);
    $.getJSON(url, function (data) {
        if (data.status == 'OK') {
            $.each(data['data'], function(key, val) {
                //console.log(val);
                // plot graph
                var placeholder = $("#datevis" + key + "x");

                //set up the hasFilter variable
                var hasFilter = true;

                //set the has filter
                if (val['min'] == 0 && val['max']== 0) {
                    hasFilter = false;
                }

                //check if the min and max value have been set otherwise set them to the ends of the graph
                if (val['min'] == 0) {
                    val['min'] = val['data'][0][0] - 5;
                }
                if (val['max']== 0) {
                    val['max'] =  parseInt(val['data'][val['data'].length - 1][0], 10) + 5;
                }

                if (zooming) {
                    //check the first and last elements of the data array against min and max value (+padding)
                    //if the element exists leave it, otherwise create a new marker with a minus one value
                    if (val['data'][val['data'].length - 1][0] != parseInt(val['max'], 10) + 5) {
                        val['data'].push([parseInt(val['max'], 10) + 5, -1]);
                    }
                    if (val['data'][0][0] != val['min'] - 5) {
                        val['data'].push([val['min'] - 5, -1]);
                    }
                    //check for values outside the selected range and remove them by setting them to null
                    for (i=0; i<val['data'].length; i++) {
                        if (val['data'][i][0] < val['min'] -5 || val['data'][i][0] > parseInt(val['max'], 10) + 5) {
                            //remove this
                            val['data'].splice(i,1);
                            i--;
                        }
                    }

                } else {
                    //no zooming means that we need to specifically set the margins
                    //do the last one first to avoid getting the new last element
                    val['data'].push([parseInt(val['data'][val['data'].length - 1][0], 10) + 5, -1]);
                    //now get the first element
                    val['data'].push([val['data'][0][0] - 5, -1]);
                }


                var plot = $.plot(placeholder, [val], options);
                if (hasFilter) {
                    // mark pre-selected area
                    plot.setSelection({ x1: val['min'] , x2: val['max']});
                }
                // selection handler
                placeholder.bind("plotselected", function (event, ranges) {
                    from = Math.floor(ranges.xaxis.from);
                    to = Math.ceil(ranges.xaxis.to);
                    location.href = val['removalURL'] + '&daterange[]=' + key + '&' + key + 'to=' + PadDigits(to,4) + '&' + key + 'from=' + PadDigits(from,4);
                });

                if (hasFilter) {
                    var newdiv = document.createElement('div');
                    var text = document.getElementById("clearButtonText").innerHTML;
                    newdiv.setAttribute('id', 'clearButton' + key);
                    newdiv.innerHTML = '<a href="' + htmlEncode(val['removalURL']) + '">' + text + '</a>';
                    newdiv.className += "dateVisClear";
                    placeholder.append(newdiv);
                }
            });
        }
    });
}

function PadDigits(n, totalDigits) 
{ 
    if (n <= 0){
        n= 1;
    }
    n = n.toString(); 
    var pd = ''; 
    if (totalDigits > n.length) 
    { 
        for (i=0; i < (totalDigits-n.length); i++) 
        { 
            pd += '0'; 
        } 
    } 
    return pd + n; 
}