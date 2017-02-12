@extends('layouts.master')

@section('content')

    <h1>{{ $repo->owner }}/{{ $repo->name }}</h1>

    <div id="rq1" style="margin-bottom: 50px;">
        <div class="title"></div>
        <div class="row">
            <div class="col-xs-12 col-md-6">
                <canvas width="400" height="250"></canvas>
            </div>
        </div>
    </div>

    <div id="rq2" style="margin-bottom: 50px;">
        <div class="title"></div>
        <div class="row">
            <div class="col-xs-12 col-md-8">
                <div id="heatMap"></div>
            </div>
            <div class="col-xs-12 col-md-4">
                <div class="v_types" style="max-height: 400px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>

    <div id="rq3" style="margin-bottom: 50px;">
        <div class="title"></div>
        <div class="row">
            <div class="col-xs-12 col-md-6">
                <canvas width="400" height="250"></canvas>
            </div>
        </div>
    </div>

    <div id="rq4">
        <div class="title"></div>
        <div class="row">
            <div class="col-xs-12 col-md-6">
                <canvas width="400" height="250"></canvas>
            </div>
        </div>
    </div>

@endsection

<script src="https://code.jquery.com/jquery-2.2.4.min.js"
        integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44="
        crossorigin="anonymous"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"
        integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa"
        crossorigin="anonymous"></script>
<script src="/js/chart.min.js"></script>
<script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
<script>

    $(document).ready(function () {

        //RQ1
        $.ajax({
            url: 'http://githubgrabber.dev/results/rq1/{{ $repo->id }}',
            dataType: 'json',
            type: 'GET',
            error: function() {
                console.error('error!');
            },
            success: function(data) {
                var options = {};
                var ctx = $('#rq1 canvas');
                $('#rq1 .title').html(data.rq);
                var myBarChart = new Chart(ctx, {
                    type: 'bar',
                    data: data,
                    options: options
                });
            }
        });

        //RQ2
        $.ajax({
            url: 'http://githubgrabber.dev/results/rq2/{{ $repo->id }}',
            dataType: 'json',
            type: 'GET',
            error: function() {
                console.error('error!');
            },
            success: function(data) {

                $('#rq2 .title').html(data.rq);

                var vtypes = '';
                for (var key in data.w) {
                    vtypes += '<div><u>' + key + '</u>: ' + data.w[key] + '</div>';
                }
                $('#rq2 .v_types').html(vtypes);

                var heatmap = data.heatMap;
                Plotly.newPlot('heatMap', heatmap);

            }
        });

        //RQ3
        $.ajax({
            url: 'http://githubgrabber.dev/results/rq3/{{ $repo->id }}',
            dataType: 'json',
            type: 'GET',
            error: function() {
                console.error('error!');
            },
            success: function(data) {
                var options = {};
                var ctx = $('#rq3 canvas');
                $('#rq3 .title').html(data.rq);
                var myBarChart = new Chart(ctx, {
                    type: 'bar',
                    data: data,
                    options: options
                });
            }
        });

        //RQ4
        $.ajax({
            url: 'http://githubgrabber.dev/results/rq4/{{ $repo->id }}',
            dataType: 'json',
            type: 'GET',
            error: function() {
                console.error('error!');
            },
            success: function(data) {
                var options = {};
                var ctx = $('#rq4 canvas');
                $('#rq4 .title').html(data.rq);
                var myPieChart = new Chart(ctx,{
                    type: 'bar',
                    data: data,
                    options: options
                });
            }
        });
    });

</script>
