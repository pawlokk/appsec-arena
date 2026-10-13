package com.arena.seam.service;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Component;

import java.util.List;
import java.util.Map;

@Component
public class ReportScheduler {

    private final JdbcTemplate appJdbc;
    private final JdbcTemplate aggregationJdbc;

    @Autowired
    private ProjectService projectService;

    public ReportScheduler(
            JdbcTemplate appJdbc,
            @Qualifier("analyticsJdbcTemplate") JdbcTemplate aggregationJdbc) {
        this.appJdbc = appJdbc;
        this.aggregationJdbc = aggregationJdbc;
    }

    @Scheduled(fixedDelayString = "${app.scheduler.interval:30000}")
    public void processScheduledReports() {
        List<Map<String, Object>> jobs = appJdbc.queryForList(
            "SELECT * FROM report_schedules WHERE status = 'pending' ORDER BY created_at ASC LIMIT 5"
        );

        for (Map<String, Object> job : jobs) {
            try {
                Long tenantId = ((Number) job.get("tenant_id")).longValue();
                String sortPreference = (String) job.get("sort_preference");

                Long projectId = job.get("project_id") != null
                    ? ((Number) job.get("project_id")).longValue() : null;
                if (projectId != null) {
                    projectService.getReportsForProject(projectId);
                }

                List<Map<String, Object>> results = aggregateReports(tenantId, sortPreference);

                String summary = "Generated " + results.size() + " rows";
                appJdbc.update(
                    "UPDATE report_schedules SET status = 'done', result = ? WHERE id = ?",
                    summary, job.get("id")
                );
            } catch (Exception e) {
                appJdbc.update(
                    "UPDATE report_schedules SET status = 'error', status_detail = ? WHERE id = ?",
                    (e.getMessage() != null && e.getMessage().length() > 256
                        ? e.getMessage().substring(0, 256) : e.getMessage()), job.get("id")
                );
            }
        }
    }

    public List<Map<String, Object>> aggregateReports(Long tenantId, String sortField) {
        String sql = String.format(
            "SELECT * FROM reports WHERE tenant_id = %d ORDER BY %s",
            tenantId, sortField
        );
        return aggregationJdbc.queryForList(sql);
    }

    public List<Map<String, Object>> findAllSorted(Long tenantId, String sortField) {
        return aggregateReports(tenantId, sortField);
    }
}
