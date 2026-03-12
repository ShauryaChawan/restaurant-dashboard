# Recharts Implementation & Backend API Structure

This document explains how the **Recharts** library is used in the frontend (`resources/js`) and how the backend API provides data for these charts. It's meant to help you understand the data flow and how Recharts works in the context of this project.

## 1. What is Recharts?

[Recharts](https://recharts.org/) is a composable charting library built on React components. It uses SVG elements to render charts and provides declarative components like `<BarChart>`, `<LineChart>`, `<XAxis>`, `<Tooltip>`, etc.

The main idea behind Recharts is that you pass an **array of data objects** to a main chart wrapper (e.g., `<BarChart data={...}>`), and then child components like `<Bar>`, `<Line>`, `<XAxis>` use the `dataKey` prop to determine which property of the object to display on that axis or series.

---

## 2. Frontend Implementation (`resources/js/components/charts`)

There are three main chart components in this project. They all receive a `data` prop, which is an array of objects directly from the backend API.

### A. `DailyOrdersChart.jsx` (Line Chart)
Displays the number of orders per day.
- **Component used:** `<LineChart>`
- **Expected `data` prop:** An array of objects, e.g., `[{ date: "2024-05-01", count: 12 }, ...]`
- **How it works:**
  - `<XAxis dataKey="date" />`: Maps the x-axis to the `date` property.
  - `<Line dataKey="count" />`: Maps the y-axis line values to the `count` property.

### B. `DailyRevenueChart.jsx` (Bar Chart)
Displays the total revenue per day.
- **Component used:** `<BarChart>`
- **Expected `data` prop:** An array of objects, e.g., `[{ date: "2024-05-01", revenue: 4500.50 }, ...]`
- **How it works:**
  - `<XAxis dataKey="date" />`: Maps the x-axis to the `date` property.
  - `<Bar dataKey="revenue" />`: Renders bars based on the `revenue` property.

### C. `PeakHourChart.jsx` (Bar Chart)
Displays the peak order hour for each day.
- **Component used:** `<BarChart>`
- **Expected `data` prop:** An array of objects, e.g., `[{ date: "2024-05-01", peak_hour: 19, order_count: 15 }, ...]`
- **How it works:**
  - `<XAxis dataKey="date" />`: Maps the x-axis to the `date` property.
  - `<YAxis domain={[0, 23]} />`: Sets the y-axis to represent the 24 hours of a day.
  - `<Bar dataKey="peak_hour" />`: Renders bars based on the `peak_hour` property.
  - The component also uses the `order_count` property to customize the tooltip and highlight the bar with the absolute highest number of orders overall.

---

## 3. Backend API Response Structure (`app/Http/Controllers/Api/V1/AnalyticsController.php`)

The data for these charts is provided by the `AnalyticsController` via the `GET /api/v1/analytics/restaurant/{restaurant}` endpoint. The core logic resides in `AnalyticsService`.

The API expects `start_date` and `end_date` parameters and returns a JSON response containing the data arrays needed by the charts.

### Example API Response
```json
{
  "success": true,
  "message": "Restaurant analytics fetched successfully.",
  "data": {
    "restaurant": {
      "id": 1,
      "name": "Example Restaurant",
      ...
    },
    "daily_orders": [
      {
        "date": "2024-05-01",
        "count": 10
      },
      ...
    ],
    "daily_revenue": [
      {
        "date": "2024-05-01",
        "revenue": 1500.50
      },
      ...
    ],
    "peak_hours": [
      {
        "date": "2024-05-01",
        "peak_hour": 19,
        "order_count": 5
      },
      ...
    ],
    "avg_order_value": 150.05
  }
}
```

### Data Flow Summary
1. The frontend Dashboard makes a request to `GET /api/v1/analytics/restaurant/{id}?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD`.
2. The backend `AnalyticsService` queries the database, grouping and aggregating data by date to produce `daily_orders`, `daily_revenue`, and `peak_hours` arrays.
3. The frontend receives the response and extracts the arrays (e.g., `data.daily_orders`).
4. These arrays are passed directly to the chart components (e.g., `<DailyOrdersChart data={data.daily_orders} />`).
5. Recharts reads the keys defined in `dataKey` (like `date`, `count`, `revenue`) from each object in the array and draws the SVGs accordingly.
