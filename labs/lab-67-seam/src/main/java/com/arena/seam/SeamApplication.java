package com.arena.seam;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.boot.context.properties.ConfigurationProperties;
import org.springframework.boot.jdbc.DataSourceBuilder;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Primary;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.scheduling.annotation.EnableScheduling;

import javax.sql.DataSource;

@SpringBootApplication
@EnableScheduling
public class SeamApplication {

    public static void main(String[] args) {
        SpringApplication.run(SeamApplication.class, args);
    }

    @Bean(name = "analyticsDataSource")
    @ConfigurationProperties(prefix = "app.analytics.datasource")
    public DataSource analyticsDataSource() {
        return DataSourceBuilder.create().build();
    }

    @Bean(name = "analyticsJdbcTemplate")
    public JdbcTemplate analyticsJdbcTemplate(@org.springframework.beans.factory.annotation.Qualifier("analyticsDataSource") DataSource ds) {
        return new JdbcTemplate(ds);
    }
}
