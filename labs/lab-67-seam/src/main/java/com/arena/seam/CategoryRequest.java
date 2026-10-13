package com.arena.seam;

import jakarta.validation.constraints.Pattern;

public class CategoryRequest {
    @Pattern(regexp = "^[a-zA-Z0-9_-]+$", message = "Invalid category format")
    private String category;

    public String getCategory() { return category; }
    public void setCategory(String category) { this.category = category; }
}
