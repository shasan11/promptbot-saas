export default function SecretInput(props) {
    return (
        <input
            type="password"
            autoComplete="new-password"
            className="w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-950 focus:ring-slate-950"
            {...props}
        />
    );
}
