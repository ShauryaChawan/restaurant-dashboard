import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';

export default function DailyOrdersChart({ data = [] }) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5">
      <h3 className="text-sm font-semibold text-gray-700 mb-4">Daily Order Count</h3>
      {data.length === 0 ? (
        <p className="text-sm text-gray-400 text-center py-8">No data for this range</p>
      ) : (
        <ResponsiveContainer width="100%" height={220}>
          <LineChart data={data} margin={{ top: 5, right: 10, left: -10, bottom: 5 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
            <XAxis
              dataKey="date"
              tick={{ fontSize: 11, fill: '#6b7280' }}
              tickFormatter={(d) => d.slice(5)}
            />
            <YAxis tick={{ fontSize: 11, fill: '#6b7280' }} allowDecimals={false} />
            <Tooltip
              contentStyle={{ fontSize: 12, borderRadius: '8px', border: '1px solid #e5e7eb' }}
              labelFormatter={(d) => `Date: ${d}`}
              formatter={(v) => [v, 'Orders']}
            />
            <Line
              type="monotone"
              dataKey="count"
              stroke="#111827"
              strokeWidth={2}
              dot={{ r: 3, fill: '#111827' }}
              activeDot={{ r: 5 }}
            />
          </LineChart>
        </ResponsiveContainer>
      )}
    </div>
  );
}
