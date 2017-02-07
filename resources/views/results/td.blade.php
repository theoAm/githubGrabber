@extends('layouts.master')

@section('content')

    <h1>{{ $repo->owner }}/{{ $repo->name }}</h1>

    <div id="rq1">
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

    });

</script>
