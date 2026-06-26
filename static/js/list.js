window.pageLoadFiles = [
    'DataTable',
    'Request',
    'Layer',
];

window.pageOnLoad = function () {
    var logTimer = null;
    var currentLogId = '';
    var logLayerId = null;

    function statusText(s) {
        return s === 'running' ? '运行中' : (s === 'done' ? '已完成' : (s === 'failed' ? '失败' : s));
    }

    function statusTag(s) {
        var cls = s === 'done' ? 'success' : (s === 'failed' ? 'error' : (s === 'running' ? 'info' : 'neutral'));
        return '<span class="tag tag-' + cls + '">' + statusText(s) + '</span>';
    }

    function fmtTime(ms) {
        return ms ? $.formatDateTime(new Date(ms)) : '-';
    }

    function fmtDuration(row) {
        if (!row.start) return '-';
        var end = row.end || Date.now();
        var sec = Math.max(0, Math.round((end - row.start) / 1000));
        if (sec < 60) return sec + ' 秒';
        if (sec < 3600) return Math.floor(sec / 60) + ' 分 ' + (sec % 60) + ' 秒';
        return Math.floor(sec / 3600) + ' 时 ' + Math.floor((sec % 3600) / 60) + ' 分';
    }


    var table = new DataTable('#dataTable');
    table.load({
        uri: '/tasks/api/list',
        height: 'auto',
        lineHeight: 'auto',
        mobile: true,
        page: false,
        selectable: false,
        break: false,
        events: {
            onRowClick: function (row) {
                if (row && row.id) openLogs(row);
            }
        },
        columns: [
            { field: 'name', name: '任务名称', align: 'center', width: 'auto' },
            {
                field: 'pid', name: 'PID', align: 'center', width: '90px',
                formatter: function (v) { return v ? v : '-'; }
            },
            {
                field: 'status', name: '状态', align: 'center', width: '100px',
                formatter: function (v) { return statusTag(v); }
            },
            {
                field: 'start', name: '开始时间', align: 'center', width: '180px',
                formatter: function (v) { return fmtTime(v); }
            },
            {
                field: 'duration', name: '耗时', align: 'center', width: '120px',
                formatter: function (v, row) { return fmtDuration(row); }
            },
            {
                field: 'op', name: '日志', align: 'center', width: '120px',
                formatter: function () { return '<mdui-button variant="text" icon="article">查看</mdui-button>'; }
            },
        ],
    });


    function renderLogs(record) {
        var logs = (record && record.logs) || [];
        var html = logs.map(function (l) {
            var t = new Date(l.t).toLocaleTimeString();
            return '<div class="task-log-line ' + $.escapeHtml(l.level) + '">'
                + '<span class="task-log-time">' + $.escapeHtml(t) + '</span>'
                + $.escapeHtml(l.msg) + '</div>';
        }).join('');
        $('#taskLogContent').html(html || '暂无日志');
        var body = $('#taskLogBody')[0];
        if (body) body.scrollTop = body.scrollHeight;
        $('#taskLogTitle').text((record.name || '任务') + ' · ' + statusText(record.status));
    }

    function stopPolling() {
        if (logTimer) { clearInterval(logTimer); logTimer = null; }
    }

    function fetchLog() {
        if (!currentLogId) return;
        $.request.get('/tasks/api/detail', { id: currentLogId }, function (res) {
            if (res.code === 200 && res.data) {
                renderLogs(res.data);
                if (res.data.status !== 'running') stopPolling();
            }
        });
    }

    function openLogs(row) {
        currentLogId = row.id;
        logLayerId = $.layer.html({
            title: '任务日志',
            content: '<div id="taskLogTitle" class="title-medium mb-2"></div>'
                + '<div id="taskLogBody"><pre class="task-log" id="taskLogContent">加载中…</pre></div>',
            style: 'width: min(680px, 92vw);',
            closeOnOverlayClick: true,
            onClosed: function () {
                currentLogId = '';
                stopPolling();
            }
        });
        fetchLog();
        stopPolling();
        logTimer = setInterval(fetchLog, 2000);
    }

    $('#refreshTable').on('click', function () {
        table.reload({}, true);
    });

    window.pageOnUnLoad = function () {
        stopPolling();
        if (logLayerId) $.layer.close(logLayerId);
    };
};
