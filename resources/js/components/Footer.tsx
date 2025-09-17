export function Footer() {
  const links = [
    { name: "About", href: "#" },
    { name: "Blog", href: "#" },
    { name: "Privacy", href: "#" },
    { name: "Terms", href: "#" },
    { name: "Contact", href: "#" }
  ];

  return (
    <footer className="w-full bg-white border-t border-[#E6E9ED] py-12">
      <div className="max-w-6xl mx-auto px-6">
        <div className="flex flex-col md:flex-row justify-between items-center gap-6">
          {/* Logo */}
          <div className="flex items-center">
            <div className="w-8 h-8 bg-[#2D5BFF] rounded-lg flex items-center justify-center">
              <span className="text-white font-bold text-sm mono">AF</span>
            </div>
            <span className="ml-3 text-[#1A1A1A] font-semibold">AppliFlow</span>
          </div>

          {/* Links */}
          <nav className="flex gap-8">
            {links.map((link) => (
              <a
                key={link.name}
                href={link.href}
                className="text-[#4A4A4A] hover:text-[#1A1A1A] transition-colors"
              >
                {link.name}
              </a>
            ))}
          </nav>
        </div>

        <div className="mt-8 pt-8 border-t border-[#E6E9ED] text-center">
          <p className="text-[#7A7A7A] text-sm">
            © 2024 AppliFlow. All rights reserved.
          </p>
        </div>
      </div>
    </footer>
  );
}