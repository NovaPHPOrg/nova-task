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
        if (s === 'running') return '运行中';
        if (s === 'done') return '已完成';
        if (s === 'failed') return '失败';
        if (s === 'killed') return '已终止';
        return s;
    }

    function statusTag(s) {
        var cls = s === 'done' ? 'success' : (s === 'failed' || s === 'killed' ? 'error' : (s === 'running' ? 'info' : 'neutral'));
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

    function actionButtons(row, index) {
        var html = '<mdui-button-icon data-index="' + index + '" icon="article" class="action-log" title="查看日志"></mdui-button-icon>';
        if (row.status === 'running') {
            html += '<mdui-button-icon data-index="' + index + '" icon="stop_circle" class="action-kill" title="强制终止"></mdui-button-icon>';
        } else {
            html += '<mdui-button-icon data-index="' + index + '" icon="delete" class="action-remove" title="清理记录"></mdui-button-icon>';
        }
        return html;
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
                field: 'op', name: '操作', align: 'center', width: '140px',
                formatter: function (v, row, index) { return actionButtons(row, index); }
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

    $('#dataTable').on('click', '.action-log, .action-kill, .action-remove', function (e) {
        e.stopPropagation();
        var row = table.getRow($(this).data('index'));
        if (!row) {
            $.toaster.error('无法获取任务信息');
            return;
        }
        if ($(this).hasClass('action-log')) {
            openLogs(row);
            return;
        }
        if ($(this).hasClass('action-kill')) {
            $.layer.confirm({
                msg: '将强制终止任务「' + (row.name || '') + '」，是否继续？',
                yes: function () {
                    $.request.postForm('/tasks/api/kill', { id: row.id }, function (res) {
                        if (res.code === 200) {
                            $.toaster.success(res.msg || '任务已终止');
                            table.reload({}, true);
                        } else {
                            $.toaster.error(res.msg || '终止失败');
                        }
                    });
                }
            });
            return;
        }
        $.layer.confirm({
            msg: '将清理任务「' + (row.name || '') + '」的记录，是否继续？',
            yes: function () {
                $.request.postForm('/tasks/api/remove', { id: row.id }, function (res) {
                    if (res.code === 200) {
                        $.toaster.success(res.msg || '已清理');
                        table.reload({}, true);
                    } else {
                        $.toaster.error(res.msg || '清理失败');
                    }
                });
            }
        });
    });

    $('#refreshTable').on('click', function () {
        table.reload({}, true);
    });

    $('#cleanupFinished').on('click', function () {
        $.layer.confirm({
            msg: '将清理全部已完成、失败或已终止的任务记录，运行中的任务不会受影响，是否继续？',
            yes: function () {
                $.request.postForm('/tasks/api/cleanup', {}, function (res) {
                    if (res.code === 200) {
                        $.toaster.success(res.msg || '清理完成');
                        table.reload({}, true);
                    } else {
                        $.toaster.error(res.msg || '清理失败');
                    }
                });
            }
        });
    });

    window.pageOnUnLoad = function () {
        stopPolling();
        if (logLayerId) $.layer.close(logLayerId);
    };
};
