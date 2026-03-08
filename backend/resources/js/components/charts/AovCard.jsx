export default function AovCard({ value = 0 }) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5 flex flex-col justify-between">
      <h3 className="text-sm font-semibold text-gray-700">Avg. Order Value</h3>
      <div className="mt-4">
        <span className="text-3xl font-bold text-gray-900">
          ₹{Number(value).toLocaleString('en-IN', { maximumFractionDigits: 2 })}
        </span>
        <p className="text-xs text-gray-400 mt-1">across selected date range</p>
      </div>
    </div>
  );
}
