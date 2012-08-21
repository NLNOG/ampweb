<?
    require("amplib.php");
    templateTop();

    $baseurl = "http://amp.ring.nlnog.net/data";
/*
    $data = array("response" => 
	array("sites" => 
	    array("sitename1", "sitename2", "...", "sitenameN")
	)
    );
    function print_xml($data) {
	
	if ( is_array($data) ) {
	    foreach($data as $tag=>$child) {
		if ( !is_numeric($tag) )
		    echo "&lt;$tag&gt;";
		print_xml($child);
		if ( !is_numeric($tag) )
		    echo "&lt;/$tag&gt;\n";
	    }
	} else {
	    echo "$data";
	}
    }
*/
?>

<div style="text-align:left">

<h1>AMP External API Documentation</h1>

<p>
<ol>
<li><a href="apidoc.php#keys">API Keys</a></li>
<ol>
<li><a href="apidoc.php#getkey">How to get an API key</a></li>
<li><a href="apidoc.php#guestkey">Guest API Key</a></li>
</ol>
<li><a href="apidoc.php#getdata">GET data</a></li>
<ol>
<li><a href="apidoc.php#formats">Format types</a></li>
<li><a href="apidoc.php#sources">Get all sources</a></li>
<li><a href="apidoc.php#destinations">Get all destinations from a source</a></li>
<li><a href="apidoc.php#tests">Get all tests between a source and destination pair</a></li>
<li><a href="apidoc.php#subtypes">Get all test subtypes for a given test between a source and destination pair</a></li>
<li><a href="apidoc.php#data">Get data for a source/destination/test/subtype combination</a></li>
</ol>
</ol>




<h2>API Keys</h2>

<p>
API keys are used to control access to the data, and, by limiting the amount of
data that can be retrieved during a certain time period, help keep the service
responsive to all those using it.
</p>


<p id="getkey">
<strong>How to get an API Key</strong>
</p>
<p>
Currently the only way to get access to a key is to request one from us via
email, or to use a guest key. Mail <code>bcj3</code> AT 
<code>cs.waikato.ac.nz</code> 
to request a key, or for more information about this API.
</p>

<p id="guestkey">
<strong>Guest API Key</strong>
</p>
<p>
There is a guest key with limited access to the data that may be used for
testing or evaluation purposes. Use the key "<code>guest</code>" in place
of a personal key to get access to up to one hour of data at a time.
</p>





<h2 id="getdata">GET data</h2>
<p>
All data is accessed by sending an HTTP GET request to the appropriate URL.
In the case of an error you will still receive a response (HTTP 200 OK) but
the message will contain error fields like the following:
</p>
<p>
XML response:
<pre class="apicode"><code>
&lt;error&gt;
    &lt;code&gt;numericcode&lt;/code&gt;
    &lt;message&gt;informative message&lt;/message&gt;
&lt;/error&gt;
</code>
</pre>


JSON response:
<pre class="apicode"><code>
{
    "error":{
	"code":numericcode,
	"message":"informative message"
    }
}
</code>
</pre>

Text response:
<pre class="apicode"><code>
informative message
</code>
</pre>

</p>




<h2 id="formats">Format types</h2>
<p>
There are currently three formats data can be returned in: <code>xml</code>,
<code>json</code> and <code>text</code>.
</p>




<!-- GET ALL SOURCES -->

<h2 id="sources">Get all sources:</h2>
<p>
<code>
<? echo "$baseurl/&lt;format&gt;/;api_key=guest"; ?>
</code>
</p>
<p>
<ul>
<li><code>&lt;format&gt;:</code> desired response format, ie <code>xml</code>, <code>json</code> or <code>text</code></li>
</ul>
</p>

<p>
XML response:
<pre class="apicode"><code>
&lt;response&gt;
    &lt;sites&gt;
	&lt;site&gt;sitename1&lt;/site&gt;
	&lt;site&gt;sitename2&lt;/site&gt;
	...
	&lt;site&gt;sitenameN&lt;/site&gt;
    &lt;/sites&gt;
&lt;/response&gt;
</code>
</pre>


JSON response:
<pre class="apicode"><code>
{
    "response":{
	"sites":["sitename1", "sitename2", ..., "sitenameN"]
    }
}
</code>
</pre>

Text response:
<pre class="apicode"><code>
sitename1
sitename2
...
sitenameN
</code>
</pre>

</p>



<!-- GET ALL DESTINATIONS -->

<h2 id="destinations">Get all destinations from a source:</h2>
<p>
<code>
<? echo "$baseurl/&lt;format&gt;/&lt;source&gt;/;api_key=guest"; ?>
</code>
</p>
<p>
<ul>
<li><code>&lt;format&gt;:</code> desired response format, ie <code>xml</code>, <code>json</code> or <code>text</code></li>
<li><code>&lt;source&gt;:</code> name of source host to fetch data for, eg <code>ampz-waikato</code></li>
</ul>
</p>

<p>
XML response:
<pre class="apicode"><code>
&lt;response&gt;
    &lt;sites src="sourcesitename"&gt;
	&lt;site&gt;sitename1&lt;/site&gt;
	&lt;site&gt;sitename2&lt;/site&gt;
	...
	&lt;site&gt;sitenameN&lt;/site&gt;
    &lt;/sites&gt;
&lt;/response&gt;
</code>
</pre>


JSON response:
<pre class="apicode"><code>
{
    "response":{
	"sites":["sitename1", "sitename2", ..., "sitenameN"]
    }
}
</code>
</pre>

Text response:
<pre class="apicode"><code>
# src='sourcesitename'
sitename1
sitename2
...
sitenameN
</code>
</pre>

</p>



<!-- GET ALL TESTS -->

<h2 id="tests">Get all tests between a source and destination pair:</h2>
<p>
<code>
<? echo "$baseurl/&lt;format&gt;/&lt;source&gt;/&lt;destination&gt;/;api_key=guest"; ?>
</code>
</p>
<p>
<ul>
<li><code>&lt;format&gt;:</code> desired response format, ie <code>xml</code>, <code>json</code> or <code>text</code></li>
<li><code>&lt;source&gt;:</code> name of source host to fetch data for, eg <code>ampz-waikato</code></li>
<li><code>&lt;destination&gt;:</code> name of destination host to fetch data for, eg <code>ampz-auckland</code></li>
</ul>
</p>

<p>
XML response:
<pre class="apicode"><code>
&lt;response&gt;
    &lt;tests src="sourcesitename" dst="destinationsitename"&gt;
	&lt;test&gt;
	    &lt;id&gt;internalid1&lt;/id&gt;
	    &lt;name&gt;testname1&lt;/name&gt;
	&lt;test&gt;
	&lt;test&gt;
	    &lt;id&gt;internalid2&lt;/id&gt;
	    &lt;name&gt;testname2&lt;/name&gt;
	&lt;test&gt;
	...
	&lt;test&gt;
	    &lt;id&gt;internalidN&lt;/id&gt;
	    &lt;name&gt;testnameN&lt;/name&gt;
	&lt;test&gt;
    &lt;/tests&gt;
&lt;/response&gt;
</code>
</pre>


JSON response:
<pre class="apicode"><code>
{
    "response":{
	"tests":["testname1", "testname2", ..., "testnameN"]
    }
}
</code>
</pre>

Text response:
<pre class="apicode"><code>
# src='sourcesitename' dst='destinationsitename'
internalid1 testname1 
internalid2 testname2 
...
internalidN testnameN
</code>
</pre>

</p>


<!-- GET ALL TEST SUBTYPES -->

<h2 id="subtypes">Get all test subtypes for a given test between a source and destination pair:</h2>
<p>
<code>
<? echo "$baseurl/&lt;format&gt;/&lt;source&gt;/&lt;destination&gt;/&lt;test&gt;/;api_key=guest"; ?>
</code>
</p>
<p>
<ul>
<li><code>&lt;format&gt;:</code> desired response format, ie <code>xml</code>, <code>json</code> or <code>text</code></li>
<li><code>&lt;source&gt;:</code> name of source host to fetch data for, eg <code>ampz-waikato</code></li>
<li><code>&lt;destination&gt;:</code> name of destination host to fetch data for, eg <code>ampz-auckland</code></li>
<li><code>&lt;test&gt;:</code> name of test to fetch data for, eg <code>icmp</code></li>
</ul>
</p>

<p>
XML response:
<pre class="apicode"><code>
&lt;response&gt;
    &lt;subtypes src="sourcesitename" dst="destinationsitename" test="testname"&gt;
	&lt;subtype&gt;subtype1&lt;/subtype&gt;
	&lt;subtype&gt;subtype2&lt;/subtype&gt;
	...
	&lt;subtype&gt;subtypeN&lt;/subtype&gt;
    &lt;/subtypes&gt;
&lt;/response&gt;
</code>
</pre>

JSON response:
<pre class="apicode"><code>
{
    "response":{
	"subtypes":["subtype1", "subtype2", ..., "subtypeN"]
    }
}
</code>
</pre>

Text response:
<pre class="apicode"><code>
# src='sourcesitename' dst='destinationsitename' test='testname'
subtype1
subtype2
...
subtypeN
</code>
</pre>
</p>





<!-- GET DATA -->
<!-- XXX dont forget binsize, stats, summary -->

<h2 id="data">Get data for a source/destination/test/subtype combination</h2>
<p>
<code>
<? echo "$baseurl/&lt;format&gt;/&lt;source&gt;/&lt;destination&gt;/&lt;test&gt;/&lt;subtype&gt;/&lt;starttime&gt;/&lt;endtime&gt;/;api_key=guest[&binsize=&lt;binsize&gt;][&stat=&lt;stat&gt;]"; ?>
</code>
</p>

<p>
<ul>
<li><code>&lt;format&gt;:</code> desired response format, ie <code>xml</code>, <code>json</code> or <code>text</code></li>
<li><code>&lt;source&gt;:</code> name of source host to fetch data for, eg <code>ampz-waikato</code></li>
<li><code>&lt;destination&gt;:</code> name of destination host to fetch data for, eg <code>ampz-auckland</code></li>
<li><code>&lt;test&gt;:</code> name of test to fetch data for, eg <code>icmp</code></li>
<li><code>&lt;subtype&gt;:</code> name of test subtype to fetch data for, eg <code>0084</code></li>
<li><code>&lt;starttime&gt;:</code> earliest unix timestamp to consider, eg <code>1264396027</code></li>
<li><code>&lt;endtime&gt;:</code> latest unix timestamp to consider, eg <code>1264397227</code></li>
<li><code>&lt;binsize&gt;:</code> number of seconds per bin, eg <code>300</code></li>
<li><code>&lt;stat&gt;:</code> summary statistics to report, ie <code>mean</code>, <code>max</code>, <code>min</code>, <code>jitter</code>, <code>loss</code> or <code>all</code> </li>
</ul>
</p>


<p>
XML response:
<pre class="apicode"><code>
&lt;response&gt;
    &lt;dataset src="sourcesitename" dst="destinationsitename" test="testname" subtype="subtypename" start="starttime" end="endtime" binsize="secondsperbin"&gt;
	&lt;data&gt;
	    &lt;time&gt;time1&lt;/time&gt;
	    &lt;stat1 missing="numbermissing" count="numberpresent"&gt;
		&lt;mean&gt;mean1&lt;/mean&gt;
		...
	    &lt;/stat1&gt;
	    &lt;stat2 missing="numbermissing" count="numberpresent"&gt;
		&lt;mean&gt;mean2&lt;/mean&gt;
		...
	    &lt;/stat2&gt;
	    ...
	    &lt;statN missing="numbermissing" count="numberpresent"&gt;
		&lt;mean&gt;meanN&lt;/mean&gt;
	    &lt;/statN&gt;
	&lt;/data&gt;
	...
	&lt;data&gt;
	    &lt;time&gt;timeN&lt;/time&gt;
	    &lt;stat1 missing="numbermissing" count="numberpresent"&gt;
		&lt;mean&gt;mean1&lt;/mean&gt;
		...
	    &lt;/stat1&gt;
	    &lt;stat2 missing="numbermissing" count="numberpresent"&gt;
		&lt;mean&gt;mean2&lt;/mean&gt;
		...
	    &lt;/stat2&gt;
	    ...
	    &lt;statN missing="numbermissing" count="numberpresent"&gt;
		&lt;mean&gt;meanN&lt;/mean&gt;
		...
	    &lt;/statN&gt;
	&lt;/data&gt;
    &lt;/dataset&gt;
&lt;/response&gt;
</code>
</pre>


JSON response:
<pre class="apicode"><code>
{
    "response":{
	"dataset":[
	    {
		"data":{
		    "time":time1,
		    "stat1":{
			"missing":missing1,
			"count":count1,
			"mean":mean1,
			...
		    }
		    "stat2":{
			"missing":missing2,
			"count":count2,
			"mean":mean2,
			...
		    }
		    ...
		    "statN":{
			"missing":missingN,
			"count":countN,
			"mean":meanN,
			...
		    }
		}
	    },
	    ...
	    {
		"data":{
		    "time":timeN,
		    "stat1":{
			"missing":missing1,
			"count":count1,
			"mean":mean1,
			...
		    }
		    "stat2":{
			"missing":missing2,
			"count":count2,
			"mean":mean2,
			...
		    }
		    ...
		    "statN":{
			"missing":missingN,
			"count":countN,
			"mean":meanN,
			...
		    }
		}
	    }
	]
    }
}
</code>
</pre>

Text response:
<pre class="apicode"><code>
# src='sourcesitename' dst='destinationsitename' start='starttime' end='endtime' test='testname' subtype='subtypename' binsize='secondsperbin'
timestamp,src,dst,testType,testSubType,missing_stat1,count_stat1,mean_stat1, ... missing_statN,count_statN,mean_statN
time1,src,dst,testname,subtypename,missing1,count1,mean1, ... missingN,countN,meanN
time2,src,dst,testname,subtypename,missing2,count2,mean2, ... missingN,countN,meanN
...
timeN,src,dst,testname,subtypename,missingN,countN,meanN, ... missingN,countN,meanN
</code>
</pre>

</p>

</div>

<?
endPage();
?>
