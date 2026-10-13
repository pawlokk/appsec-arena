using Microsoft.Data.Sqlite;
using Newtonsoft.Json;

namespace Loam.BackgroundWorker;

public class TaskProcessor : BackgroundService
{
    private readonly string _dbPath;
    private readonly ILogger<TaskProcessor> _logger;
    private readonly TimeSpan _interval;

    public TaskProcessor(LoamConfig cfg, ILogger<TaskProcessor> logger)
    {
        _dbPath   = cfg.DbPath;
        _logger   = logger;
        _interval = TimeSpan.FromMilliseconds(
            int.TryParse(Environment.GetEnvironmentVariable("TASK_PROCESSOR_INTERVAL_MS"), out var a) ? a : 20000
        );
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _logger.LogInformation("TaskProcessor started, interval={interval}ms", _interval.TotalMilliseconds);
        while (!stoppingToken.IsCancellationRequested)
        {
            await Pp();
            await Task.Delay(_interval, stoppingToken);
        }
    }

    private async Task Pp()
    {
        using var c = new SqliteConnection($"Data Source={_dbPath}");
        await c.OpenAsync();

        var s = c.CreateCommand();
        s.CommandText = "SELECT id, payload_json FROM task_queue WHERE status = 'pending' LIMIT 5";
        var r = new System.Collections.Generic.List<(long id, string payload)>();
        using (var x = await s.ExecuteReaderAsync())
        {
            while (await x.ReadAsync())
                r.Add((x.GetInt64(0), x.GetString(1)));
        }

        foreach (var (i, b) in r)
        {
            string v = "completed";
            string? n = null;
            try
            {
                var o = JsonConvert.DeserializeObject(b, new JsonSerializerSettings
                {
                    TypeNameHandling = TypeNameHandling.All
                });
                n = o?.GetType().Name ?? "null";
                _logger.LogInformation("TaskProcessor: processed job {id}, type={type}", i, n);
            }
            catch (Exception ex)
            {
                v = "failed";
                n = ex.Message;
                _logger.LogWarning("TaskProcessor: job {id} failed: {err}", i, ex.Message);
            }

            var u = c.CreateCommand();
            u.CommandText = "UPDATE task_queue SET status = $status, result = $result, processed_at = datetime('now') WHERE id = $id";
            u.Parameters.AddWithValue("$status", v);
            u.Parameters.AddWithValue("$result", n ?? "");
            u.Parameters.AddWithValue("$id", i);
            await u.ExecuteNonQueryAsync();
        }
    }
}
